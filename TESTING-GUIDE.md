# 🎯 Complete Testing Command Reference
## Operaton DMN Evaluator Plugin

### 🚀 **Quick Start Commands**

#### **Daily Development**
```bash
# Quick feedback during development (fastest)
./run-tests.sh quick
# OR
composer run dev
# OR
npm run dev

# Standard development testing (recommended)
./run-tests.sh standard
# OR
composer run test:comprehensive
```

#### **Before Committing**
```bash
# Pre-commit validation
composer run pre-commit
npm run pre-commit

# OR use automatic pre-commit hooks
git commit -m "feat: your changes"  # Hooks run automatically
```

#### **Before Releasing**
```bash
# Full validation
./run-tests.sh full
# OR
composer run pre-release
npm run pre-release

# Extreme resilience testing
./run-tests.sh extreme
# OR
npm run test:extreme
```

---

## 🧪 **Test Categories & Commands**

### **1. Unit & Integration Tests (PHP)**

```bash
# Basic PHP testing
composer run test                    # All PHP tests
composer run test:unit              # Unit tests only
composer run test:integration       # Integration tests only

# Specific test suites
composer run test:api               # REST API tests
composer run test:mock              # Mock service tests
composer run test:performance       # Performance benchmarks
composer run test:security          # Security validation
```

### **2. End-to-End Testing (Browser)**

```bash
# Cypress E2E Testing
npm run cypress:open                # Open Cypress GUI
npm run cypress:run                 # Run headless
npm run cypress:live                # Test against live environment

# Playwright Cross-Browser Testing
npm run playwright:test             # Run cross-browser tests
npm run playwright:ui               # Open Playwright UI
npm run playwright:headed           # Run with visible browser
npm run playwright:debug            # Debug mode

# Combined E2E Testing
npm run test:e2e                    # Cypress only
npm run test:e2e:all                # Both Cypress and Playwright
npm run test:e2e:cross-browser      # Playwright cross-browser
```

### **3. Load Testing (K6)**

```bash
# Load testing scenarios
npm run test:load                   # Standard load test
npm run test:load:smoke             # Quick health check
npm run test:load:evaluation        # DMN evaluation focused
npm run test:load:stress            # High-stress testing
npm run test:load:report            # Generate detailed reports
```

### **4. Chaos Engineering**

```bash
# Resilience testing
npm run test:chaos                  # Full chaos testing
npm run test:chaos:dev              # Development environment
npm run test:chaos:staging          # Staging environment
npm run chaos:baseline              # Quick health baseline
```

### **5. Comprehensive Test Suites**

```bash
# Orchestrated test combinations
composer run test:comprehensive     # Standard comprehensive
npm run test:comprehensive          # Same as above
composer run test:quick             # Unit tests only (fastest)
composer run test:full              # Include load testing
composer run test:extreme           # Everything including chaos
```

---

## 📋 **Test Execution Strategies**

### **By Development Phase**

#### **During Active Development**
```bash
# Fastest feedback (< 5 seconds)
./run-tests.sh quick
composer run dev

# Quick validation (< 30 seconds)
composer run test:unit
npm run test:e2e
```

#### **Before Code Review**
```bash
# Standard validation (< 2 minutes)
./run-tests.sh standard
composer run check
npm run check
```

#### **Before Release**
```bash
# Full validation (< 10 minutes)
./run-tests.sh full
composer run pre-release
npm run pre-release

# Complete resilience testing (< 20 minutes)
./run-tests.sh extreme
npm run test:extreme
```

### **By Environment**

#### **Development Environment**
```bash
composer run development            # Quick PHP tests
npm run development                 # Quick E2E tests
npm run test:chaos:dev              # Development chaos testing
```

#### **Staging Environment**
```bash
composer run staging                # Full PHP + load tests
npm run staging                     # Full testing suite
npm run test:chaos:staging          # Staging chaos testing
```

#### **Production Environment**
```bash
composer run production             # Conservative testing
npm run production                  # Full comprehensive suite
npm run chaos:baseline              # Health monitoring only
```

---

## 🔧 **Quality & Maintenance Commands**

### **Code Quality**
```bash
# Linting and formatting
composer run lint                   # Check code style
composer run lint:fix               # Auto-fix issues
composer run format                 # Fix + summary
composer run quality                # Lint + security audit
```

### **Security**
```bash
composer run security               # Security audit
composer run audit                  # Same as security
composer run quality:strict         # Strict quality gates
```

### **Pre-commit Hooks**
```bash
composer run hooks:enable           # Enable git hooks
composer run hooks:disable          # Disable git hooks
composer run hooks:status           # Check hook status
composer run hooks:test             # Test hooks manually
composer run hooks:setup            # Initial setup
```

### **Mock Service**
```bash
composer run mock:demo              # Demo mock service
composer run mock:test              # Test mock service
```

---

## 🚀 **CI/CD Integration Commands**

### **For CI/CD Pipelines**
```bash
# Standard CI pipeline
composer run ci                     # Comprehensive testing
npm run ci                          # Same as above

# Quick CI (for fast feedback)
composer run ci:quick               # PHP unit + E2E
npm run ci:quick                    # Quick validation

# Full CI (for release branches)
composer run ci:full                # Include load testing
npm run ci:full                     # Full validation

# E2E only (for UI changes)
npm run ci:e2e-only                 # E2E tests only

# Safe CI (when integration is unstable)
composer run ci:safe                # Unit tests only
```

---

## 📊 **Test Reporting & Utilities**

### **Generate Reports**
```bash
npm run test:reports                # Generate comprehensive reports
npm run test:load:report            # Detailed load test reports
npm run test:validate               # Validation suite with reports
```

### **Health Monitoring**
```bash
npm run test:health                 # Quick health check
npm run chaos:baseline              # System baseline metrics
```

### **Cleanup**
```bash
npm run clean:reports               # Clean all test artifacts
```

---

## 🎯 **Recommended Daily Workflow**

### **Morning Setup**
```bash
# Quick health check
npm run test:health
composer run hooks:status

# If working on new features
./run-tests.sh quick
```

### **During Development**
```bash
# After each significant change
composer run dev                    # Quick PHP validation
npm run dev                         # Quick E2E validation
```

### **Before Committing**
```bash
# Pre-commit validation (automatic if hooks enabled)
composer run pre-commit
git commit -m "feat: your changes"
```

### **End of Day**
```bash
# Comprehensive validation
./run-tests.sh standard
composer run check
```

### **Before Releases**
```bash
# Full validation including resilience
./run-tests.sh extreme
composer run pre-release
npm run pre-release
```

---

## 🌟 **Pro Tips**

### **Performance Optimization**
- Use `./run-tests.sh quick` during active development
- Run `./run-tests.sh standard` before committing
- Use `npm run test:load:smoke` for quick performance checks
- Reserve `./run-tests.sh extreme` for release validation

### **Debugging Failed Tests**
```bash
# Verbose output for specific components
composer run test:api:verbose
npm run playwright:debug
npm run playwright:headed
```

### **Environment Configuration**
```bash
# Set environment variables for different targets
export DMN_TEST_URL="https://your-test-site.com"
export DMN_API_KEY="your-api-key"
export TEST_ENV="development"  # or staging, production
```

### **Parallel Execution**
- Most test commands support parallel execution
- Playwright automatically runs cross-browser tests in parallel
- K6 load tests simulate concurrent users

---

## 📈 **Expected Execution Times**

| Command | Duration | Use Case |
|---------|----------|----------|
| `./run-tests.sh quick` | < 5s | Active development |
| `./run-tests.sh standard` | < 2min | Pre-commit |
| `./run-tests.sh full` | < 10min | Pre-release |
| `./run-tests.sh extreme` | < 20min | Full validation |
| `npm run test:e2e:all` | < 30s | UI validation |
| `npm run test:load:smoke` | < 10s | Quick perf check |
| `npm run test:chaos` | 5-15min | Resilience testing |
