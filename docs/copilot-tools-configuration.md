# GitHub Copilot Tools Configuration

This document explains how to configure which tools are enabled by default for GitHub Copilot agents working on this repository.

## Overview

GitHub Copilot agents can use various tools (MCP - Model Context Protocol servers) to help with development tasks. You can control which tools are enabled by default through the `.github/copilot-config.yml` file.

## Configuration File

The configuration is stored in `.github/copilot-config.yml`:

```yaml
# GitHub Copilot Configuration
# Configure which tools are enabled by default for Copilot agents

tools:
  # GitHub API tool - for repository operations
  github:
    enabled: true
  
  # Filesystem tool - for reading/writing files
  filesystem:
    enabled: true
  
  # Browser tool - for web browsing and screenshots
  browser:
    enabled: true
  
  # Bash tool - for running shell commands
  bash:
    enabled: true
```

## Available Tools

### GitHub Tool
- **Purpose**: Interact with GitHub API (issues, PRs, commits, etc.)
- **Use cases**: Fetching issue details, listing pull requests, checking repository status
- **Enabled by default**: Yes

### Filesystem Tool
- **Purpose**: Read and write files in the repository
- **Use cases**: Viewing source code, creating/editing files, checking directory structure
- **Enabled by default**: Yes

### Browser Tool
- **Purpose**: Web browsing and taking screenshots
- **Use cases**: Viewing documentation, testing web interfaces, capturing UI changes
- **Enabled by default**: Yes

### Bash Tool
- **Purpose**: Execute shell commands
- **Use cases**: Running tests, building code, installing dependencies, running CLI tools
- **Enabled by default**: Yes

## How to Configure

To enable or disable a tool:

1. Open `.github/copilot-config.yml`
2. Find the tool you want to configure
3. Set `enabled: true` to enable or `enabled: false` to disable
4. Commit and push your changes

### Example: Disable Browser Tool

```yaml
tools:
  browser:
    enabled: false  # Changed from true to false
```

### Example: Enable Only Specific Tools

```yaml
tools:
  github:
    enabled: true
  filesystem:
    enabled: true
  browser:
    enabled: false  # Disabled
  bash:
    enabled: true
```

## Best Practices

1. **Keep GitHub and Filesystem enabled**: These are essential for most development tasks
2. **Enable Bash for testing**: Needed to run tests, linters, and build commands
3. **Browser tool**: Useful for UI work and documentation viewing, but can be disabled if not needed
4. **Review changes**: When changing tool configuration, test with a simple task to ensure Copilot still has the tools it needs

## Troubleshooting

### Copilot can't perform a task
- Check if the required tool is enabled in `.github/copilot-config.yml`
- Enable the tool and commit the change
- The agent will have access to the tool in future sessions

### Too many tools enabled
- Consider disabling tools you don't use for your workflow
- This can help focus the agent on the most relevant capabilities

## Related Files

- `.github/copilot-instructions.md` - Custom instructions for how Copilot should work on this codebase
- `.github/copilot-config.yml` - Tool configuration (this file)

## Further Reading

For more information about GitHub Copilot configuration and MCP tools, refer to GitHub's official documentation.
