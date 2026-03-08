<?php

declare(strict_types=1);

namespace App\Command;

use App\A2AGateway\A2AClient;
use App\A2AGateway\SkillCatalogBuilder;
use App\LLM\LiteLlmClient;
use App\LLM\LlmRequestContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'agent:chat',
    description: 'Interactive chat with LLM that can invoke platform agent skills',
)]
final class AgentChatCommand extends Command
{
    private const MAX_TOOL_ITERATIONS = 10;

    /** @var array<string, string> openai_function_name => platform_skill_id */
    private array $toolNameMap = [];

    private ChatUserContext $userContext;

    private bool $debug = false;

    public function __construct(
        private readonly LiteLlmClient $llmClient,
        private readonly SkillCatalogBuilder $skillCatalogBuilder,
        private readonly A2AClient $a2aClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'Username for CLI context')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Preferred language (uk, en)')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Show detailed debug output (trace_id, request_id, full payloads)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->debug = (bool) $input->getOption('debug');

        $io->title('Agent Chat');
        $io->text('<fg=gray>(type "exit" or Ctrl+C to quit)</>');

        $this->userContext = $this->collectUserContext($input, $io);

        $io->text(sprintf(
            '<fg=green>Session:</> user=<fg=cyan>%s</>, lang=<fg=cyan>%s</>, platform=<fg=cyan>%s</>',
            $this->userContext->username,
            $this->userContext->language,
            $this->userContext->platform,
        ));
        $io->newLine();

        $catalog = $this->skillCatalogBuilder->build();
        /** @var list<array<string, mixed>> $platformTools */
        $platformTools = $catalog['tools'] ?? [];
        $openAiTools = $this->convertToOpenAiTools($platformTools);
        $this->toolNameMap = $this->buildToolNameMap($platformTools);

        if ([] === $openAiTools) {
            $output->writeln('<fg=yellow>Warning: No agent skills available. Enable agents first.</>');
        } else {
            $output->writeln(sprintf('<fg=green>%d tool(s) loaded from platform agents.</>', count($openAiTools)));
            foreach ($platformTools as $tool) {
                $output->writeln(sprintf('  <fg=gray>- %s</> (%s)', (string) ($tool['name'] ?? ''), (string) ($tool['agent'] ?? '')));
            }
        }
        $output->writeln('');

        /** @var list<array<string, mixed>> $messages */
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($platformTools)],
        ];

        $traceId = bin2hex(random_bytes(16));

        if ($this->debug) {
            $output->writeln(sprintf('<fg=gray>trace_id: %s</>', $traceId));
            $output->writeln(sprintf('<fg=gray>logs:    /admin/logs/trace/%s</>', $traceId));
            $output->writeln('');
        }

        while (true) {
            $userInput = $this->readUserInput($output);

            if (null === $userInput) {
                break;
            }

            $trimmed = trim($userInput);
            if ('' === $trimmed) {
                continue;
            }
            if ('exit' === strtolower($trimmed) || 'quit' === strtolower($trimmed)) {
                break;
            }

            $messages[] = ['role' => 'user', 'content' => $trimmed];
            $messages = $this->runInferenceLoop($messages, $openAiTools, $traceId, $output);
        }

        $output->writeln('');
        $output->writeln('<fg=cyan>Goodbye!</>');

        return Command::SUCCESS;
    }

    private function collectUserContext(InputInterface $input, SymfonyStyle $io): ChatUserContext
    {
        $usernameOption = $input->getOption('username');
        if (\is_string($usernameOption) && '' !== trim($usernameOption)) {
            $username = trim($usernameOption);
        } else {
            $defaultUsername = get_current_user();
            $username = (string) $io->ask('Your username', $defaultUsername, static function (?string $value): string {
                $value = trim((string) $value);
                if ('' === $value) {
                    throw new \RuntimeException('Username cannot be empty.');
                }

                return $value;
            });
        }

        $languageOption = $input->getOption('language');
        if (\is_string($languageOption) && \in_array($languageOption, ['uk', 'en'], true)) {
            $language = $languageOption;
        } else {
            $language = (string) $io->choice('Preferred language', ['uk', 'en'], 'uk');
        }

        return new ChatUserContext(
            username: $username,
            language: $language,
        );
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $openAiTools
     *
     * @return list<array<string, mixed>>
     */
    private function runInferenceLoop(
        array $messages,
        array $openAiTools,
        string $traceId,
        OutputInterface $output,
    ): array {
        $iteration = 0;
        $actor = 'cli:'.$this->userContext->username;

        while ($iteration < self::MAX_TOOL_ITERATIONS) {
            ++$iteration;

            $llmContext = new LlmRequestContext(
                agentName: 'core',
                featureName: 'core.agent_chat',
                requestId: 'chat_'.bin2hex(random_bytes(8)),
                traceId: $traceId,
                sessionId: $traceId,
                userId: $actor,
            );

            try {
                $response = $this->llmClient->chatCompletion($messages, $openAiTools, $llmContext);
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<fg=red>LLM error: %s</>', $e->getMessage()));
                break;
            }

            /** @var array<string, mixed> $choice */
            $choice = $response['choices'][0] ?? [];
            /** @var array<string, mixed> $assistantMessage */
            $assistantMessage = $choice['message'] ?? [];
            $finishReason = (string) ($choice['finish_reason'] ?? '');

            $messages[] = $assistantMessage;

            /** @var list<array<string, mixed>> $toolCalls */
            $toolCalls = $assistantMessage['tool_calls'] ?? [];

            if ([] === $toolCalls || 'tool_calls' !== $finishReason) {
                $content = (string) ($assistantMessage['content'] ?? '');
                $output->writeln(sprintf('<fg=blue;options=bold>Assistant:</> %s', $content));
                break;
            }

            foreach ($toolCalls as $toolCall) {
                $toolCallId = (string) ($toolCall['id'] ?? '');
                /** @var array<string, mixed> $function */
                $function = $toolCall['function'] ?? [];
                $functionName = (string) ($function['name'] ?? '');
                $argumentsJson = (string) ($function['arguments'] ?? '{}');

                $skillId = $this->toolNameMap[$functionName] ?? str_replace('_', '.', $functionName);

                /** @var array<string, mixed> $arguments */
                $arguments = (array) json_decode($argumentsJson, true, 512, JSON_THROW_ON_ERROR);
                $arguments = $this->enrichWithUserContext($arguments);

                $requestId = 'chat_'.bin2hex(random_bytes(8));

                if ($this->debug) {
                    $output->writeln(sprintf(
                        '<fg=yellow>  [tool] %s</> <fg=gray>%s</> <fg=gray>req=%s</>',
                        $skillId,
                        $argumentsJson,
                        $requestId,
                    ));
                } else {
                    $output->writeln(sprintf('<fg=yellow>  [tool] %s</>', $skillId));
                }

                try {
                    $result = $this->a2aClient->invoke($skillId, $arguments, $traceId, $requestId, $actor);
                } catch (\Throwable $e) {
                    $result = ['status' => 'failed', 'error' => $e->getMessage()];
                }

                $resultJson = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $status = (string) ($result['status'] ?? 'unknown');
                $agent = (string) ($result['agent'] ?? 'unknown');
                $durationMs = (int) ($result['duration_ms'] ?? 0);

                $statusColor = 'completed' === $status ? 'green' : 'red';
                if ($this->debug) {
                    $output->writeln(sprintf(
                        '  <fg=%s>[%s]</> <fg=gray>%s %dms</>',
                        $statusColor,
                        $status,
                        $agent,
                        $durationMs,
                    ));

                    /** @var array<string, mixed> $toolResult */
                    $toolResult = $result['result'] ?? [];
                    if ([] !== $toolResult) {
                        $toolResultJson = json_encode($toolResult, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        $output->writeln(sprintf('  <fg=gray>%s</>', $toolResultJson));
                    }
                } else {
                    $output->writeln(sprintf(
                        '  <fg=%s>[%s]</> <fg=gray>%s</>',
                        $statusColor,
                        $status,
                        $agent,
                    ));
                }
                if ('failed' === $status) {
                    $output->writeln(sprintf(
                        '  <fg=red>error: %s</>',
                        (string) ($result['error'] ?? $result['reason'] ?? 'unknown'),
                    ));
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => $resultJson,
                ];
            }
        }

        if ($iteration >= self::MAX_TOOL_ITERATIONS) {
            $output->writeln('<fg=red>Warning: Maximum tool call iterations reached.</>');
        }

        return $messages;
    }

    /**
     * Enrich tool call arguments with CLI user context (non-destructive merge).
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function enrichWithUserContext(array $arguments): array
    {
        if (!isset($arguments['author'])) {
            $arguments['author'] = ['username' => $this->userContext->username];
        }
        if (!isset($arguments['platform'])) {
            $arguments['platform'] = $this->userContext->platform;
        }
        if (!isset($arguments['metadata'])) {
            $arguments['metadata'] = [];
        }
        /** @var array<string, mixed> $metadata */
        $metadata = $arguments['metadata'];
        if (!isset($metadata['channel'])) {
            $metadata['channel'] = $this->userContext->platform.'.interactive';
            $arguments['metadata'] = $metadata;
        }

        return $arguments;
    }

    private function readUserInput(OutputInterface $output): ?string
    {
        $line = readline('You: ');

        if (false === $line) {
            return null;
        }

        return $line;
    }

    /**
     * @param list<array<string, mixed>> $platformTools
     *
     * @return list<array<string, mixed>>
     */
    private function convertToOpenAiTools(array $platformTools): array
    {
        $openAiTools = [];

        foreach ($platformTools as $tool) {
            $name = (string) ($tool['name'] ?? '');
            $description = (string) ($tool['description'] ?? '');
            $agent = (string) ($tool['agent'] ?? '');
            /** @var array<string, mixed> $inputSchema */
            $inputSchema = (array) ($tool['input_schema'] ?? ['type' => 'object']);

            $functionName = str_replace('.', '_', $name);

            if ('' !== $agent) {
                $description .= sprintf(' (agent: %s)', $agent);
            }

            $openAiTools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $functionName,
                    'description' => $description,
                    'parameters' => $inputSchema,
                ],
            ];
        }

        return $openAiTools;
    }

    /**
     * @param list<array<string, mixed>> $platformTools
     *
     * @return array<string, string>
     */
    private function buildToolNameMap(array $platformTools): array
    {
        $map = [];
        foreach ($platformTools as $tool) {
            $name = (string) ($tool['name'] ?? '');
            $functionName = str_replace('.', '_', $name);
            $map[$functionName] = $name;
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $platformTools
     */
    private function buildSystemPrompt(array $platformTools): string
    {
        $toolList = '';
        foreach ($platformTools as $tool) {
            $toolList .= sprintf(
                "\n- %s (agent: %s): %s",
                (string) ($tool['name'] ?? ''),
                (string) ($tool['agent'] ?? ''),
                (string) ($tool['description'] ?? ''),
            );
        }

        $languageLabel = match ($this->userContext->language) {
            'uk' => 'Ukrainian',
            'en' => 'English',
            default => $this->userContext->language,
        };

        return <<<PROMPT
            You are an AI assistant on the AI Community Platform. You help users by answering questions and using available agent skills (tools) when appropriate.

            Current user context:
            - Username: {$this->userContext->username}
            - Preferred language: {$languageLabel}
            - Platform: {$this->userContext->platform}

            Available tools:{$toolList}

            Guidelines:
            - Use tools when the user's request matches a tool's capability.
            - When calling a tool, provide the required parameters as described in the tool's schema.
            - After receiving tool results, present them directly to the user without rephrasing or duplicating the content. Do not re-generate or paraphrase what the agent already produced.
            - If a tool call fails, explain the error and suggest alternatives.
            - Be concise and helpful.
            - Respond in {$languageLabel} unless the user explicitly switches language.
            - When a tool accepts author/platform context fields, include the current user context.
            PROMPT;
    }
}
