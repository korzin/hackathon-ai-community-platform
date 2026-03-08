// E2E: Admin agent enable/disable toggle
// Tests that an agent can be enabled and disabled via the admin UI.

Feature('Admin: Agent Enable/Disable Toggle');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();

    // Accept all confirm() dialogs automatically
    I.usePlaywrightTo('auto-accept dialogs', async ({ page }) => {
        page.on('dialog', async (dialog) => {
            await dialog.accept();
        });
    });
});

Scenario(
    'can enable and disable knowledge-agent',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        await agentsPage.switchToInstalled();

        // Pre-condition: knowledge-agent exists
        agentsPage.seeAgent('knowledge-agent');

        // Ensure agent starts disabled (may already be enabled from e2e-prepare)
        const enabledCount = await I.grabNumberOfVisibleElements(
            '//tr[@data-agent-name="knowledge-agent"]//span[contains(@class,"badge-enabled")]',
        );
        if (enabledCount > 0) {
            await agentsPage.disableAgent('knowledge-agent');
            await I.waitForElement(
                '//tr[@data-agent-name="knowledge-agent"]//span[contains(@class,"badge-disabled")]',
                10,
            );
        }
        agentsPage.seeAgentDisabled('knowledge-agent');

        // Step 1: Enable the agent
        await agentsPage.enableAgent('knowledge-agent');

        // Wait for page to reload after fetch + location.reload()
        await I.waitForElement('//tr[@data-agent-name="knowledge-agent"]//span[contains(@class,"badge-enabled")]', 10);

        // Verify enabled state
        agentsPage.seeAgentEnabled('knowledge-agent');

        // Step 2: Disable the agent
        await agentsPage.disableAgent('knowledge-agent');

        // Wait for page to reload
        await I.waitForElement('//tr[@data-agent-name="knowledge-agent"]//span[contains(@class,"badge-disabled")]', 10);

        // Verify disabled state restored
        agentsPage.seeAgentDisabled('knowledge-agent');
    },
).tag('@admin');
