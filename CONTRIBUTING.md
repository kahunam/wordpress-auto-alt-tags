# Contributing to WordPress Auto Alt Tags

🎉 Thank you for considering contributing to the WordPress Auto Alt Tags plugin! We welcome contributions from the community and are grateful for any help you can provide.

## 🚀 How to Contribute

### 🐛 Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When creating a bug report, include:

- **WordPress version**
- **PHP version**
- **Plugin version**
- **Clear description** of the issue
- **Steps to reproduce** the problem
- **Expected vs actual behavior**
- **Screenshots** if applicable
- **Error messages** from debug logs

### 💡 Suggesting Features

We love new ideas! When suggesting features:

- **Check existing issues** to avoid duplicates
- **Clearly describe** the feature and its benefits
- **Explain the use case** - why would this be useful?
- **Consider implementation** - is it technically feasible?

### 🔧 Development Setup

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/wordpress-auto-alt-tags.git
   ```
3. **Set up WordPress locally** (using Local, XAMPP, Docker, etc.)
4. **Install the plugin** in your development environment:
   ```bash
   cd wp-content/plugins/
   ln -s /path/to/your/clone auto-alt-tags
   ```
5. **Get a Gemini API key** from [Google AI Studio](https://ai.google.dev/)
6. **Configure the API key** in your development environment

### 🛠️ Making Changes

1. **Create a feature branch** from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
2. **Make your changes** following our coding standards
3. **Test thoroughly** with different scenarios
4. **Write or update tests** if applicable
5. **Update documentation** if needed
6. **Commit with descriptive messages**:
   ```bash
   git commit -m "Add feature: descriptive summary of changes"
   ```

### 📝 Coding Standards

#### PHP Code Style
- Follow **WordPress Coding Standards**
- Use **proper indentation** (tabs, not spaces)
- Include **PHPDoc comments** for functions and classes
- **Sanitize and validate** all inputs
- Use **WordPress functions** instead of native PHP when available

#### JavaScript Code Style
- Use **modern ES6+ syntax** where supported
- Follow **WordPress JavaScript standards**
- Include **JSDoc comments** for functions
- Use **jQuery** for DOM manipulation (WordPress standard)
- Handle **errors gracefully**

#### CSS Code Style
- Follow **WordPress CSS standards**
- Use **mobile-first responsive design**
- Include **browser prefixes** when necessary
- Organize styles **logically by component**

### 🧪 Testing

Before submitting, please test:

#### Manual Testing
- **Different WordPress versions** (5.0+)
- **Various PHP versions** (7.4+)
- **Multiple browsers** and devices
- **Large media libraries** (performance)
- **API error scenarios** (invalid keys, network issues)
- **Different image formats** and sizes

#### Test Scenarios
1. **Fresh installation** with no existing alt tags
2. **Partial processing** (stop and resume)
3. **API rate limiting** and timeouts
4. **Large batch processing** (100+ images)
5. **Network interruptions** during processing
6. **Invalid API keys** and error handling

### 📦 Pull Request Process

1. **Update documentation** if your changes affect functionality
2. **Update the changelog** with your changes
3. **Ensure all tests pass** and no conflicts exist
4. **Create a pull request** with:
   - Clear title describing the change
   - Detailed description of what was changed and why
   - Link to related issues
   - Screenshots if UI changes are involved

#### Pull Request Template
```markdown
## Description
Brief description of the changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tested on WordPress 5.0+
- [ ] Tested on PHP 7.4+
- [ ] Manual testing completed
- [ ] No console errors

## Checklist
- [ ] Code follows WordPress standards
- [ ] Documentation updated
- [ ] Changelog updated
```

### 🏷️ Issue Labels

We use labels to organize issues:

- **`bug`** - Something isn't working
- **`enhancement`** - New feature or improvement
- **`documentation`** - Documentation improvements
- **`good first issue`** - Good for newcomers
- **`help wanted`** - Extra attention needed
- **`question`** - Further information requested
- **`wontfix`** - This will not be worked on

### 🌐 Internationalization

When adding new strings:

1. **Wrap all user-facing text** in translation functions:
   ```php
   __('Text to translate', 'auto-alt-tags')
   _e('Text to echo', 'auto-alt-tags')
   ```
2. **Use the text domain** `auto-alt-tags`
3. **Provide context** when needed:
   ```php
   _x('Draft', 'post status', 'auto-alt-tags')
   ```

### 🚀 Performance Guidelines

- **Optimize database queries** - use WordPress functions when possible
- **Minimize API calls** - batch requests efficiently
- **Handle large datasets** - implement pagination/chunking
- **Cache when appropriate** - use WordPress transients
- **Profile memory usage** - avoid memory leaks in long-running processes

### 🔒 Security Best Practices

- **Validate and sanitize** all inputs
- **Use nonces** for AJAX requests
- **Check user capabilities** before operations
- **Escape output** to prevent XSS
- **Use prepared statements** for database queries
- **Follow WordPress security guidelines**

### 📋 Code Review Process

All submissions require review. The process:

1. **Automated checks** run on pull requests
2. **Manual review** by maintainers
3. **Feedback and iteration** if needed
4. **Approval and merge** when ready

### 🤝 Community Guidelines

- **Be respectful** and inclusive
- **Help others** learn and grow
- **Give constructive feedback**
- **Follow the WordPress community values**
- **Have fun** and enjoy contributing!

### 📞 Getting Help

Need help contributing?

- **Join the discussion** in GitHub issues
- **Ask questions** in pull request comments
- **Read the WordPress developer documentation**
- **Check out WordPress coding standards**

### 🎉 Recognition

Contributors will be:

- **Listed in the changelog** for their contributions
- **Mentioned in release notes** for significant features
- **Added to the contributors list** in the repository

Thank you for helping make WordPress more accessible! 🌟

---

## 📚 Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Gemini API Documentation](https://ai.google.dev/docs)
- [WordPress Accessibility Guidelines](https://developer.wordpress.org/coding-standards/accessibility/)
- [Git Workflow Best Practices](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
