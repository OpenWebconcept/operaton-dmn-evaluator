# 🎯 Complete Testing Command Reference
## Operaton DMN Evaluator Plugin - Enhanced with OpenAPI Coverage

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

### **1. Unit & Integration Tests (PHP) - ✨ Enhanced with OpenAPI Coverage**

```bash
# Basic PHP testing
composer run test                    # All PHP tests (44 unit + 16 integration with OpenAPI)
composer run test:unit              # Unit tests only (44 tests with OpenAPI data validation)
composer run test:integration       # ✨ Integration tests (16 tests with complete OpenAPI coverage)

# Specific test suites
composer run test:api               # ✨ Enhanced REST API tests (complete OpenAPI endpoint coverage)
composer run test:api:verbose       # ✨ REST API tests with detailed OpenAPI validation output
composer run test:mock              # Mock service tests
composer run test:performance       # Performance benchmarks
composer run test:security          # Security validation

# CI/CD specific commands
composer run test:ci                # CI-safe comprehensive tests
composer run test:ci-safe           # Unit tests only (safest)
```

**✨ What's Enhanced in These Commands:**
- **`composer run test:integration`** now includes:
  - Complete Operaton DMN engine connectivity (version 1.0.0-beta-4-SNAPSHOT)
  - Decision definition management (list, by-key lookup, XML retrieval)
  - Deployment lifecycle testing (12 deployments found)
  - Historical decision instance access (10 instances with timestamps)
  - Enhanced security testing (4/4 attack vectors blocked)
  - HTTP protocol compliance validation

- **`composer run test:unit`** now includes:
  - OpenAPI data type validation (String, Integer, Boolean, Double, Date)
  - Complete DMN decision table testing (all 6 rules)
  - Variable type validation and error scenarios
  - Internationalization support (German, French, Spanish)
  - Performance testing and evaluation history
  - Result schema validation and compliance

### **2. End-to-End Form Workflow Testing (Browser)**

#### **🍽️ Cypress - Form Workflow Testing**
```bash
# Interactive development and debugging
npm run cypress:open                # Open Cypress GUI for form testing
npm run cypress:run                 # Run all headless Cypress tests
npm run cypress:live                # Test all specs against live environment

# Specific dish form workflow testing
npm run cypress:dish-form           # Run dish form workflow test only
npm run cypress:dish-form:live      # Run dish form test against live environment

# Specific DMN API testing
npm run cypress:dmn-api             # Run DMN API tests only
npm run cypress:dmn-api:live        # Run DMN API tests against live environment

# All Cypress specifications
npm run cypress:all-specs           # Run all Cypress specs
npm run cypress:all-specs:live      # Run all Cypress specs against live environment

# Form workflow aliases
npm run test:e2e                    # Standard Cypress form tests
npm run test:e2e:live               # Live environment form testing
```

#### **🌐 Playwright - Cross-Browser Form Testing**
```bash
# Cross-browser form workflow validation
npm run playwright:test             # Run all cross-browser tests
npm run playwright:ui               # Open Playwright UI for debugging
npm run playwright:headed           # Run with visible browser (see forms)
npm run playwright:debug            # Debug mode with breakpoints

# Specific dish form workflow testing
npm run playwright:dish-form        # Run dish form workflow in Playwright
npm run playwright:dish-form:ui     # Run dish form with Playwright UI
npm run playwright:dish-form:headed # Run dish form with visible browser
npm run playwright:dish-form:debug  # Debug mode for dish form tests
npm run playwright:specific-test    # Run specific test (e.g., line 30)

# Cross-browser aliases
npm run test:e2e:playwright         # Playwright form tests only
npm run test:e2e:cross-browser      # Cross-browser validation
```

#### **🎯 Combined E2E Form Testing**
```bash
# Complete form workflow validation
npm run test:e2e:all                # Both Cypress AND Playwright (all tests)
                                    # = Comprehensive form testing

# Specific dish form workflow testing
npm run test:e2e:dish-form          # Both Cypress AND Playwright dish form tests
npm run test:e2e:dish-form:live     # Both frameworks against live environment

# Individual framework testing
npm run test:e2e                    # Cypress only (faster for development)
npm run test:e2e:playwright         # Playwright only (cross-browser)
```

### **3. Load Testing (K6)**

```bash
# Load testing scenarios
npm run test:load                   # Standard load test
npm run test:load:smoke             # Quick health check
npm run test:load:evaluation        # DMN evaluation performance
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
composer run test:comprehensive     # ✨ Standard comprehensive (includes OpenAPI coverage)
npm run test:comprehensive          # Same as above
composer run test:quick             # Unit tests only (fastest)
composer run test:full              # ✨ Include load testing + complete OpenAPI validation
composer run test:extreme           # ✨ Everything including chaos + OpenAPI resilience
```

---

## 📋 **Form Workflow Testing Strategies**

### **🍽️ Cypress Form Testing (User Experience Focus)**

#### **Development & Debugging**
```bash
# Interactive form testing (recommended for development)
npm run cypress:open
# - Visual test runner
# - Real-time form interaction
# - Perfect for debugging form issues
# - See actual DMN evaluations happen

# Quick form validation
npm run cypress:dish-form           # Run specific dish form workflow test
npm run cypress:run                 # Run all Cypress tests
# - Headless execution
# - Fast feedback on form workflows
# - Complete DMN decision table validation

# Specific test targeting
npm run cypress:dmn-api             # Test DMN API endpoints only
npm run cypress:all-specs           # Run all Cypress specifications
```

#### **Live Environment Testing**
```bash
# Test against actual deployment
npm run cypress:dish-form:live      # Dish form workflow against live environment
npm run cypress:live                # All Cypress tests against live environment
npm run cypress:dmn-api:live        # DMN API tests against live environment
npm run cypress:all-specs:live      # All specs against live environment
# - Uses baseUrl: https://owc-gemeente.test.open-regels.nl
# - Real production-like testing
# - Validates actual DMN integration
```

### **🌐 Playwright Form Testing (Cross-Browser Focus)**

#### **Cross-Browser Validation**
```bash
# Multi-browser form testing
npm run playwright:test             # Run all cross-browser tests
npm run playwright:dish-form        # Run dish form workflow in Playwright only
# - Tests form workflow in Chrome AND Firefox
# - Validates cross-browser compatibility
# - Network request monitoring
# - Parallel execution for speed

# Visual debugging (see forms in action)
npm run playwright:headed           # Watch all tests with visible browser
npm run playwright:dish-form:headed # Watch dish form tests with visible browser
# - Watch forms being filled out
# - See DMN evaluations in real-time
# - Debug cross-browser issues

# Interactive debugging
npm run playwright:ui               # Modern UI for all tests
npm run playwright:dish-form:ui     # Modern UI for dish form tests only
# - Modern UI for test development
# - Step-by-step form workflow debugging
# - Network request inspection
```

#### **Development & Debugging**
```bash
# Debug specific form issues
npm run playwright:debug            # Debug all tests with breakpoints
npm run playwright:dish-form:debug  # Debug dish form tests specifically
npm run playwright:specific-test    # Run specific test (e.g., line 30)
# - Breakpoint support
# - Step through form interactions
# - Inspect DOM during form workflow
# - Network request analysis
```

### **🎯 When to Use Each Framework**

#### **Use Cypress (`npm run cypress:*`) When:**
- ✅ **Developing new form features**
- ✅ **Debugging form interaction issues**
- ✅ **Validating user experience**
- ✅ **Quick feedback during development**
- ✅ **Visual verification of DMN results**
- ✅ **Testing specific form workflows** (`npm run cypress:dish-form`)
- ✅ **Live environment validation** (`npm run cypress:dish-form:live`)

#### **Use Playwright (`npm run playwright:*`) When:**
- ✅ **Validating cross-browser compatibility**
- ✅ **CI/CD pipeline automation**
- ✅ **Network request monitoring**
- ✅ **Testing multiple browsers simultaneously**
- ✅ **Release validation**
- ✅ **Debugging cross-browser issues** (`npm run playwright:dish-form:debug`)
- ✅ **Visual cross-browser testing** (`npm run playwright:dish-form:headed`)

#### **Use Both (`npm run test:e2e:*`) When:**
- ✅ **Before major releases** (`npm run test:e2e:all`)
- ✅ **Comprehensive form validation** (`npm run test:e2e:dish-form`)
- ✅ **Cross-browser + user experience testing**
- ✅ **Maximum confidence testing**
- ✅ **Live environment comprehensive testing** (`npm run test:e2e:dish-form:live`)

---

## 📊 **Complete DMN Form Validation Matrix**

### **What Gets Tested in Form Workflows**

#### **✅ Cypress Tests Validate:**
- 🍽️ **Complete dish evaluation workflow** (Season → Guest Count → DMN Result)
- 🎯 **All 6 DMN decision table rules** with real form interactions
- 📡 **Network request monitoring** during form submission
- 📄 **Form navigation** between pages
- ⚡ **Real-time result population** in form fields
- 🛡️ **Error handling** with malformed inputs

#### **✅ Playwright Tests Validate:**
- 🌐 **Cross-browser form compatibility** (Chrome + Firefox)
- 📊 **DMN decision table rules** across browsers
- 🚀 **Form performance** and load times
- 🔍 **Network request capturing** and analysis
- 📝 **Form field mapping** validation
- 🎯 **Core DMN functionality** across environments

#### **✅ Combined Testing Provides:**
- 🏆 **Complete form workflow coverage**
- 📄 **User experience + technical validation**
- 🌐 **Cross-browser + single-browser testing**
- 📈 **Performance + functionality validation**
- 🛡️ **Error handling + happy path testing**

---

## 📋 **Test Execution Strategies**

### **By Development Phase**

#### **During Active Development**
```bash
# Fastest feedback (< 5 seconds)
./run-tests.sh quick
composer run dev

# Quick form validation (< 30 seconds)
npm run cypress:dish-form           # Fast dish form workflow testing
npm run cypress:run                 # All Cypress tests
composer run test:unit             # Backend logic validation (now with OpenAPI coverage)

# Specific component testing
npm run cypress:dmn-api             # DMN API testing only
npm run playwright:dish-form        # Cross-browser dish form testing
```

#### **Before Code Review**
```bash
# Standard validation (< 2 minutes)
./run-tests.sh standard
npm run test:e2e:dish-form         # Both frameworks dish form testing
npm run test:e2e                   # Cypress form testing
composer run test:api              # ✨ Enhanced API testing with OpenAPI coverage
```

#### **Before Release**
```bash
# Full validation (< 10 minutes)
./run-tests.sh full
npm run test:e2e:all               # Both Cypress AND Playwright (all tests)
npm run test:e2e:dish-form:live    # Live environment comprehensive testing
composer run pre-release

# Complete resilience testing (< 20 minutes)
./run-tests.sh extreme
npm run test:extreme
```

### **By Environment**

#### **Development Environment**
```bash
composer run development            # Quick PHP tests
npm run development                 # Quick E2E tests
npm run cypress:open               # Interactive form testing
npm run cypress:dish-form           # Quick dish form validation
npm run test:chaos:dev              # Development chaos testing
```

#### **Staging Environment**
```bash
composer run staging                # ✨ Full PHP + load tests (includes OpenAPI)
npm run staging                     # Full testing suite
npm run test:e2e:all               # Cross-browser form validation
npm run test:e2e:dish-form         # Comprehensive dish form testing
npm run test:chaos:staging          # Staging chaos testing
```

#### **Production Environment**
```bash
composer run production             # Conservative testing
npm run production                  # Full comprehensive suite
npm run cypress:dish-form:live     # Live environment dish form testing
npm run cypress:live               # Live environment form testing
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
composer run ci                     # ✨ Comprehensive testing (includes OpenAPI)
npm run ci                          # Same as above

# Quick CI (for fast feedback)
composer run ci:quick               # PHP unit + E2E
npm run ci:quick                    # Quick validation

# Full CI (for release branches)
composer run ci:full                # ✨ Include load testing + OpenAPI validation
npm run ci:full                     # Full validation

# E2E only (for UI changes)
npm run ci:e2e-only                 # Both Cypress AND Playwright

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
                                   # Removes: test-results/*, playwright-report/*,
                                   #          cypress/videos/*, cypress/screenshots/*
```

---

## 🎯 **Recommended Daily Workflow**

### **Morning Setup**
```bash
# Quick health check
npm run test:health
composer run hooks:status

# If working on form features
npm run cypress:open               # Interactive form testing
npm run cypress:dish-form           # Quick dish form validation
```

### **During Development**
```bash
# After each form change
npm run cypress:dish-form          # Quick dish form validation
npm run cypress:run                # All Cypress form tests
composer run dev                   # Quick PHP validation

# For cross-browser issues
npm run playwright:dish-form:headed # Visual cross-browser testing
npm run playwright:headed          # Visual cross-browser all tests

# For specific component testing
npm run cypress:dmn-api             # DMN API testing only
npm run playwright:dish-form       # Cross-browser dish form only
```

### **Before Committing**
```bash
# Pre-commit validation (automatic if hooks enabled)
composer run pre-commit
npm run test:e2e:dish-form         # Dish form workflow validation
npm run test:e2e                   # All Cypress form workflow validation
git commit -m "feat: your changes"
```

### **End of Day**
```bash
# Comprehensive validation
./run-tests.sh standard
npm run test:e2e:all               # Both frameworks all tests
npm run test:e2e:dish-form         # Both frameworks dish form tests
composer run check
```

### **Before Releases**
```bash
# Full validation including resilience
./run-tests.sh extreme
npm run test:e2e:all               # Complete form testing
npm run test:e2e:dish-form:live    # Live environment dish form testing
composer run pre-release
npm run pre-release
```

---

## 🌟 **Pro Tips for Enhanced Testing**

### **Performance Optimization**
- Use `npm run cypress:dish-form` during active development (fastest)
- Use `npm run test:e2e:dish-form` before committing (comprehensive)
- Use `npm run playwright:dish-form` for cross-browser validation
- Use `npm run test:e2e:dish-form:live` for live environment testing
- Reserve `./run-tests.sh extreme` for release validation

### **Debugging Form Issues**
```bash
# Visual debugging
npm run cypress:open               # See forms in real-time
npm run playwright:dish-form:headed # Watch cross-browser behavior
npm run playwright:dish-form:ui    # Modern debugging interface

# Specific component debugging
npm run cypress:dish-form           # Debug dish form workflow only
npm run cypress:dmn-api             # Debug DMN API integration only
npm run playwright:dish-form:debug  # Step-by-step dish form debugging

# Verbose output for specific components
composer run test:api:verbose      # ✨ Detailed OpenAPI validation output
npm run playwright:debug           # Step-by-step debugging all tests
npm run playwright:specific-test   # Debug specific test line
```

### **Environment Configuration**
```bash
# Set environment variables for different targets
export DMN_TEST_URL="https://your-test-site.com"
export DMN_API_KEY="your-api-key"
export TEST_ENV="development"  # or staging, production

# For live testing
npm run cypress:live               # Uses configured live URL
```

### **Enhanced Testing Best Practices**
- **Cypress for development**: Fast feedback, visual debugging (`npm run cypress:dish-form`)
- **Playwright for CI/CD**: Cross-browser reliability (`npm run playwright:dish-form`)
- **Both for releases**: Maximum confidence (`npm run test:e2e:dish-form`)
- **Live testing**: Always test against real environment before deployment (`npm run test:e2e:dish-form:live`)
- **Specific testing**: Use targeted commands for faster feedback (`npm run cypress:dmn-api`)
- **Visual debugging**: Use headed modes to see form interactions (`npm run playwright:dish-form:headed`)
- **✨ API validation**: Use `composer run test:api:verbose` for detailed OpenAPI validation

---

## 📈 **Expected Execution Times**

| Command | Duration | Use Case | Enhanced Coverage |
|---------|----------|----------|-------------------|
| `npm run cypress:dish-form` | < 5s | Quick dish form validation | Specific workflow |
| `npm run cypress:run` | < 10s | All Cypress form tests | Complete Cypress suite |
| `npm run playwright:dish-form` | < 10s | Cross-browser dish form | Specific cross-browser |
| `npm run playwright:test` | < 15s | All cross-browser forms | Complete Playwright suite |
| `npm run test:e2e:dish-form` | < 20s | Both frameworks dish form | Comprehensive dish form |
| `npm run test:e2e:all` | < 30s | Complete form testing | Both frameworks all tests |
| `composer run test:integration` | < 3s | ✨ OpenAPI endpoint validation | Complete OpenAPI coverage |
| `composer run test:unit` | < 1s | ✨ Enhanced unit tests | OpenAPI data validation |
| `composer run test:api` | < 3s | ✨ API testing with OpenAPI | Complete endpoint coverage |
| `composer run test:api:verbose` | < 5s | ✨ Detailed OpenAPI output | Verbose validation |
| `npm run cypress:live` | < 15s | Live environment Cypress | Live environment testing |
| `npm run test:e2e:dish-form:live` | < 25s | Live environment both frameworks | Live comprehensive |
| `./run-tests.sh quick` | < 5s | Active development | Enhanced unit tests |
| `./run-tests.sh standard` | < 2min | Pre-commit | Includes form + OpenAPI tests |
| `./run-tests.sh full` | < 10min | Pre-release | Full form + load + OpenAPI |
| `./run-tests.sh extreme` | < 20min | Full validation | Everything + chaos + OpenAPI |
| `npm run test:load:smoke` | < 10s | Quick perf check | API performance |
| `npm run test:chaos` | 5-15min | Resilience testing | Fault tolerance |

---

## 🎉 **Enhanced Testing Success Metrics**

### **✅ What Enhanced Tests Validate:**
- 🍽️ **Complete dish evaluation workflow** (Season → Guest Count → DMN Result → Confirmation)
- 🎯 **All 6 DMN decision table rules** with real browser interactions
- 🌐 **Cross-browser compatibility** (Chrome + Firefox validated)
- 📡 **Network request monitoring** (API calls captured and validated)
- 📄 **Form navigation** (multi-page form workflow)
- ⚡ **Real-time result population** (DMN results appear in form fields)
- 🛡️ **Error handling** (graceful degradation with invalid inputs)
- 📊 **Performance validation** (sub-second form interactions)

### **✨ Enhanced OpenAPI Coverage (via existing commands):**
- 🔧 **Complete engine information validation** (version, capabilities, availability)
- 📋 **Decision definition management** (CRUD operations, metadata, XML retrieval)
- 🚀 **Deployment lifecycle testing** (list, manage, track deployments)
- 📈 **Historical data access** (audit trails, decision instances, timestamps)
- 🔍 **Data type validation** (String, Integer, Boolean, Double, Date types)
- 🛡️ **Enhanced security testing** (SQL injection, XSS, buffer overflow, JSON injection)
- ⚡ **Performance monitoring** (response times, throughput, concurrent requests)
- 📋 **HTTP protocol compliance** (content-type, headers, status codes)

### **🏆 Production Validation:**
- ✅ **621+ successful DMN evaluations** logged in Operaton Cockpit
- ✅ **Perfect form workflow** from start to completion
- ✅ **Real user experience** validated with actual browser testing
- ✅ **Enterprise-grade reliability** across multiple browsers and environments
- ✅ **Complete OpenAPI compliance** with industry standards (via `composer run test:integration`)
- ✅ **100% security protection** against known attack vectors (via `composer run test:api`)
- ✅ **Sub-second performance** across all critical endpoints

### **📊 Complete Testing Statistics:**
- **Total Tests**: 90+ comprehensive tests
- **Unit Tests**: 44 tests (259 assertions) with OpenAPI data type coverage
- **Integration Tests**: 16 tests (39 assertions) with complete OpenAPI endpoint validation
- **E2E Tests**: 18 tests (Cypress + Playwright) with form workflow validation
- **Load Tests**: Multi-scenario performance validation
- **Chaos Tests**: Resilience and fault tolerance validation
- **Security Tests**: 100% protection against 4+ attack vectors
- **Execution Time**: 93ms unit tests, 2.6s integration tests, sub-30s comprehensive

