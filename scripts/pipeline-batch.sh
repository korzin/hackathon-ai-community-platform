#!/usr/bin/env bash
#
# Batch runner for the multi-agent pipeline.
# Reads tasks from a file and runs the pipeline for each one.
#
# Usage:
#   ./scripts/pipeline-batch.sh tasks.txt
#   ./scripts/pipeline-batch.sh tasks.txt --skip-architect
#   ./scripts/pipeline-batch.sh --no-stop-on-failure tasks.txt
#
# Task file format (one task per line, empty lines and # comments ignored):
#   Add streaming support to A2A gateway
#   Implement agent marketplace search
#   # This is a comment
#   Add retry logic to LiteLLM client
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT_DIR="$REPO_ROOT/scripts"
BATCH_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RESULTS_FILE="$REPO_ROOT/.opencode/pipeline/reports/batch_${BATCH_TIMESTAMP}.md"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Parse arguments
TASK_FILE=""
EXTRA_ARGS=""
STOP_ON_FAILURE=true
WEBHOOK_URL=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-stop-on-failure)
      STOP_ON_FAILURE=false
      shift
      ;;
    --webhook)
      WEBHOOK_URL="$1 $2"
      EXTRA_ARGS="$EXTRA_ARGS $1 $2"
      shift 2
      ;;
    --*)
      EXTRA_ARGS="$EXTRA_ARGS $1"
      if [[ "$1" == "--from" || "$1" == "--only" || "$1" == "--branch" ]]; then
        EXTRA_ARGS="$EXTRA_ARGS $2"
        shift
      fi
      shift
      ;;
    *)
      if [[ -z "$TASK_FILE" ]]; then
        TASK_FILE="$1"
      fi
      shift
      ;;
  esac
done

if [[ -z "$TASK_FILE" ]]; then
  echo -e "${RED}Error: No task file provided${NC}"
  echo ""
  echo "Usage: ./scripts/pipeline-batch.sh <tasks-file> [pipeline-options]"
  echo ""
  echo "Task file format (one task per line):"
  echo "  Add streaming support to A2A gateway"
  echo "  Implement agent marketplace search"
  echo "  # This is a comment (skipped)"
  echo ""
  echo "Options:"
  echo "  --no-stop-on-failure  Continue to next task even if current fails"
  echo ""
  echo "Pipeline options are passed through to pipeline.sh:"
  echo "  --skip-architect    Skip the architect stage"
  echo "  --from <agent>      Start from a specific agent"
  echo "  --only <agent>      Run only a specific agent"
  echo "  --audit             Add auditor quality gate"
  echo "  --webhook <url>     Send notifications"
  echo "  --telegram          Send Telegram notifications"
  exit 1
fi

if [[ ! -f "$TASK_FILE" ]]; then
  echo -e "${RED}Error: Task file not found: ${TASK_FILE}${NC}"
  exit 1
fi

mkdir -p "$(dirname "$RESULTS_FILE")"

# Read tasks (skip empty lines and comments)
mapfile -t TASKS < <(grep -v '^\s*$' "$TASK_FILE" | grep -v '^\s*#')

TOTAL=${#TASKS[@]}
PASSED=0
FAILED=0
STOPPED=false

# Save starting branch
ORIGINAL_BRANCH=$(git -C "$REPO_ROOT" branch --show-current)

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║${NC}     ${YELLOW}Batch Pipeline Runner v2${NC}                    ${CYAN}║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Task file:${NC}        ${TASK_FILE}"
echo -e "${BLUE}Total tasks:${NC}      ${TOTAL}"
echo -e "${BLUE}Stop on failure:${NC}  ${STOP_ON_FAILURE}"
echo -e "${BLUE}Extra args:${NC}       ${EXTRA_ARGS:-none}"
echo -e "${BLUE}Original branch:${NC}  ${ORIGINAL_BRANCH}"
echo -e "${BLUE}Results:${NC}          ${RESULTS_FILE}"
echo ""

# Log header
{
  echo "# Batch Pipeline Results"
  echo ""
  echo "- **Started**: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "- **Task file**: ${TASK_FILE}"
  echo "- **Total tasks**: ${TOTAL}"
  echo "- **Stop on failure**: ${STOP_ON_FAILURE}"
  echo ""
  echo "| # | Task | Status | Duration | Branch |"
  echo "|---|------|--------|----------|--------|"
} > "$RESULTS_FILE"

BATCH_START=$(date +%s)

# Run each task
for i in "${!TASKS[@]}"; do
  task="${TASKS[$i]}"
  task_num=$((i + 1))

  echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
  echo -e "${BLUE}Task ${task_num}/${TOTAL}:${NC} ${task}"
  echo -e "${CYAN}══════════════════════════════════════════════════${NC}"

  start_time=$(date +%s)

  # Generate branch name for this task
  task_slug=$(echo "$task" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g' | sed 's/--*/-/g' | sed 's/^-//' | sed 's/-$//' | cut -c1-50)
  task_branch="pipeline/${task_slug}"

  # Ensure we start from the original branch for each task
  # Pipeline.sh handles branch creation, but we need clean state
  git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true

  # shellcheck disable=SC2086
  if "$SCRIPT_DIR/pipeline.sh" --branch "$task_branch" $EXTRA_ARGS "$task"; then
    end_time=$(date +%s)
    duration=$(( end_time - start_time ))
    PASSED=$((PASSED + 1))
    echo "| ${task_num} | ${task} | ✓ PASS | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
    echo -e "${GREEN}Task ${task_num} PASSED (${duration}s)${NC}"
  else
    end_time=$(date +%s)
    duration=$(( end_time - start_time ))
    FAILED=$((FAILED + 1))
    echo "| ${task_num} | ${task} | ✗ FAIL | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
    echo -e "${RED}Task ${task_num} FAILED (${duration}s)${NC}"

    if [[ "$STOP_ON_FAILURE" == true ]]; then
      echo -e "${YELLOW}Stopping batch (--no-stop-on-failure to continue)${NC}"
      STOPPED=true

      # Mark remaining tasks as skipped
      for j in $(seq $((i + 1)) $((TOTAL - 1))); do
        remaining_task="${TASKS[$j]}"
        remaining_num=$((j + 1))
        echo "| ${remaining_num} | ${remaining_task} | — SKIP | — | — |" >> "$RESULTS_FILE"
      done

      break
    fi
  fi

  # Return to original branch for next task
  # This is safe because pipeline.sh commits all changes
  git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true

  echo ""
done

BATCH_END=$(date +%s)
BATCH_DURATION=$(( BATCH_END - BATCH_START ))

# Summary
{
  echo ""
  echo "## Summary"
  echo ""
  echo "- **Passed**: ${PASSED}/${TOTAL}"
  echo "- **Failed**: ${FAILED}/${TOTAL}"
  if $STOPPED; then
    echo "- **Skipped**: $((TOTAL - PASSED - FAILED))/${TOTAL}"
  fi
  echo "- **Total duration**: ${BATCH_DURATION}s ($(( BATCH_DURATION / 60 )) min)"
  echo "- **Completed**: $(date '+%Y-%m-%d %H:%M:%S')"
} >> "$RESULTS_FILE"

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Batch Results:${NC}"
echo -e "  ${GREEN}Passed:${NC}   ${PASSED}/${TOTAL}"
echo -e "  ${RED}Failed:${NC}   ${FAILED}/${TOTAL}"
if $STOPPED; then
  echo -e "  ${YELLOW}Skipped:${NC}  $((TOTAL - PASSED - FAILED))/${TOTAL}"
fi
echo -e "  ${BLUE}Duration:${NC} ${BATCH_DURATION}s ($(( BATCH_DURATION / 60 )) min)"
echo -e "  ${BLUE}Report:${NC}   ${RESULTS_FILE}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Return to original branch
git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true

[[ $FAILED -eq 0 ]] || exit 1
