#!/bin/bash

# Lisosoft Laravel Payment Gateway - Package Publication Helper
# This script helps with publishing the package to GitHub and Packagist

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PACKAGE_NAME="lisosoft/laravel-payment-gateway"
PACKAGE_VERSION="1.0.0"
REPO_URL="https://github.com/lisosoft/laravel-payment-gateway.git"

# Functions
print_header() {
    echo -e "${BLUE}"
    echo "========================================"
    echo "Lisosoft Payment Gateway Publication"
    echo "========================================"
    echo -e "${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

check_dependencies() {
    print_header
    print_info "Checking dependencies..."

    # Check for required commands
    local missing_deps=()

    for cmd in git composer php curl; do
        if ! command -v $cmd &> /dev/null; then
            missing_deps+=("$cmd")
        fi
    done

    if [ ${#missing_deps[@]} -ne 0 ]; then
        print_error "Missing dependencies: ${missing_deps[*]}"
        exit 1
    fi

    print_success "All dependencies found"
}

validate_package() {
    print_info "Validating package structure..."

    # Check required files
    local required_files=(
        "composer.json"
        "README.md"
        "LICENSE"
        "CHANGELOG.md"
        "src/PaymentGatewayServiceProvider.php"
        "config/payment-gateway.php"
    )

    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            print_error "Missing required file: $file"
            exit 1
        fi
    done

    # Validate composer.json
    if ! php -r "json_decode(file_get_contents('composer.json'), true); if (json_last_error() !== JSON_ERROR_NONE) { exit(1); }"; then
        print_error "Invalid composer.json"
        exit 1
    fi

    # Check PHP syntax
    print_info "Checking PHP syntax..."
    find src -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" && {
        print_error "PHP syntax errors found"
        exit 1
    }

    print_success "Package validation passed"
}

prepare_git_repository() {
    print_info "Preparing Git repository..."

    # Initialize git if not already
    if [ ! -d ".git" ]; then
        git init
        print_success "Git repository initialized"
    fi

    # Add all files
    git add .

    # Check if there are changes
    if git diff --cached --quiet; then
        print_warning "No changes to commit"
    else
        git commit -m "Release v${PACKAGE_VERSION}"
        print_success "Changes committed"
    fi

    # Create version tag
    if git rev-parse "v${PACKAGE_VERSION}" >/dev/null 2>&1; then
        print_warning "Tag v${PACKAGE_VERSION} already exists"
    else
        git tag -a "v${PACKAGE_VERSION}" -m "Version ${PACKAGE_VERSION}"
        print_success "Tag v${PACKAGE_VERSION} created"
    fi
}

create_github_repository() {
    print_info "GitHub Repository Setup"
    echo ""
    echo "To create a GitHub repository:"
    echo "1. Go to https://github.com/new"
    echo "2. Repository name: laravel-payment-gateway"
    echo "3. Description: A comprehensive payment gateway package for Laravel with support for multiple payment providers"
    echo "4. Visibility: Public"
    echo "5. DO NOT initialize with README, .gitignore, or license"
    echo "6. Click 'Create repository'"
    echo ""
    echo "After creating the repository, run the commands shown on GitHub:"
    echo ""
    echo "Or if you have the repository URL, you can run:"
    echo "  git remote add origin ${REPO_URL}"
    echo "  git push -u origin main --tags"
    echo ""
    read -p "Press Enter to continue..."
}

setup_github_actions() {
    print_info "Setting up GitHub Actions..."

    # Create workflows directory
    mkdir -p .github/workflows

    # Create CI workflow
    cat > .github/workflows/ci.yml << 'EOF'
name: CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: [8.1, 8.2, 8.3]
        laravel: [10.*, 11.*]

    steps:
    - uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php }}
        extensions: mbstring, xml, ctype, json, openssl, pdo, tokenizer
        coverage: none

    - name: Validate composer.json
      run: composer validate --strict

    - name: Cache Composer packages
      id: composer-cache
      uses: actions/cache@v3
      with:
        path: vendor
        key: ${{ runner.os }}-php-${{ matrix.php }}-${{ hashFiles('**/composer.json') }}
        restore-keys: |
          ${{ runner.os }}-php-${{ matrix.php }}-

    - name: Install dependencies
      run: |
        composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update
        composer install --prefer-dist --no-progress

    - name: Run tests
      run: vendor/bin/phpunit

    - name: Check code style
      run: |
        composer require --dev friendsofphp/php-cs-fixer
        vendor/bin/php-cs-fixer fix --dry-run --diff

  security:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v4

    - name: Security check
      run: |
        composer require --dev enlightn/security-checker
        vendor/bin/security-checker security:check composer.lock
EOF

    # Create release workflow
    cat > .github/workflows/release.yml << 'EOF'
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, ctype, json

    - name: Validate composer.json
      run: composer validate --strict

    - name: Get version
      id: get_version
      run: echo "VERSION=${GITHUB_REF#refs/tags/}" >> $GITHUB_OUTPUT

    - name: Create GitHub Release
      uses: softprops/action-gh-release@v1
      with:
        name: Release ${{ steps.get_version.outputs.VERSION }}
        draft: false
        prerelease: false
        generate_release_notes: true
      env:
        GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
EOF

    git add .github/
    git commit -m "Add GitHub Actions workflows" || true
    print_success "GitHub Actions workflows created"
}

setup_packagist() {
    print_info "Packagist Setup Instructions"
    echo ""
    echo "To publish to Packagist:"
    echo ""
    echo "1. Go to https://packagist.org/login/"
    echo "2. Log in with your GitHub account"
    echo "3. Click 'Submit' in the top navigation"
    echo "4. Enter the repository URL: ${REPO_URL}"
    echo "5. Click 'Check' then 'Submit'"
    echo ""
    echo "After submission:"
    echo "1. Go to your package page"
    echo "2. Click 'Settings'"
    echo "3. Enable 'Auto-Update'"
    echo "4. Select 'GitHub Service Hook'"
    echo "5. Save changes"
    echo ""
    read -p "Press Enter to continue..."
}

create_composer_scripts() {
    print_info "Adding Composer scripts..."

    # Read current composer.json
    COMPOSER_JSON=$(cat composer.json)

    # Check if scripts section exists
    if echo "$COMPOSER_JSON" | jq '.scripts' > /dev/null 2>&1; then
        print_success "Composer scripts already exist"
    else
        # Create updated composer.json with scripts
        echo "$COMPOSER_JSON" | jq '. + {
            "scripts": {
                "test": "vendor/bin/phpunit",
                "test-coverage": "vendor/bin/phpunit --coverage-html coverage",
                "lint": "php-cs-fixer fix --dry-run --diff",
                "format": "php-cs-fixer fix",
                "analyze": "phpstan analyse src --level=5"
            }
        }' > composer.json.new

        mv composer.json.new composer.json
        print_success "Composer scripts added"
    fi
}

generate_env_example() {
    print_info "Generating .env.example file..."

    cat > .env.example << 'EOF'
# Payment Gateway Configuration

# Default Gateway
PAYMENT_GATEWAY_DEFAULT=payfast

# PayFast Configuration
PAYFAST_ENABLED=true
PAYFAST_MERCHANT_ID=your_merchant_id
PAYFAST_MERCHANT_KEY=your_merchant_key
PAYFAST_PASSPHRASE=your_passphrase
PAYFAST_TEST_MODE=true
PAYFAST_RETURN_URL=/payment/success
PAYFAST_CANCEL_URL=/payment/cancel
PAYFAST_NOTIFY_URL=/payment/webhook/payfast

# PayStack Configuration
PAYSTACK_ENABLED=true
PAYSTACK_PUBLIC_KEY=your_public_key
PAYSTACK_SECRET_KEY=your_secret_key
PAYSTACK_MERCHANT_EMAIL=your_email
PAYSTACK_TEST_MODE=true
PAYSTACK_CALLBACK_URL=/payment/callback/paystack

# PayPal Configuration
PAYPAL_ENABLED=true
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
PAYPAL_MODE=sandbox
PAYPAL_RETURN_URL=/payment/success
PAYPAL_CANCEL_URL=/payment/cancel
PAYPAL_WEBHOOK_ID=your_webhook_id

# Stripe Configuration
STRIPE_ENABLED=true
STRIPE_PUBLISHABLE_KEY=your_publishable_key
STRIPE_SECRET_KEY=your_secret_key
STRIPE_WEBHOOK_SECRET=your_webhook_secret
STRIPE_TEST_MODE=true
STRIPE_RETURN_URL=/payment/success

# Ozow Configuration
OZOW_ENABLED=true
OZOW_SITE_CODE=your_site_code
OZOW_PRIVATE_KEY=your_private_key
OZOW_API_KEY=your_api_key
OZOW_TEST_MODE=true
OZOW_CALLBACK_URL=/payment/callback/ozow
OZOW_ERROR_URL=/payment/error

# Zapper Configuration
ZAPPER_ENABLED=true
ZAPPER_MERCHANT_ID=your_merchant_id
ZAPPER_SITE_ID=your_site_id
ZAPPER_API_KEY=your_api_key
ZAPPER_TEST_MODE=true
ZAPPER_CALLBACK_URL=/payment/callback/zapper

# Cryptocurrency Configuration
CRYPTO_ENABLED=true
CRYPTO_PROVIDER=coinbase
CRYPTO_API_KEY=your_api_key
CRYPTO_API_SECRET=your_api_secret
CRYPTO_WEBHOOK_SECRET=your_webhook_secret

# EFT/Bank Transfer Configuration
EFT_ENABLED=true
EFT_BANK_NAME="Standard Bank"
EFT_ACCOUNT_NAME="Your Business Name"
EFT_ACCOUNT_NUMBER=1234567890
EFT_BRANCH_CODE=051001
EFT_REFERENCE_PREFIX=LISO
EFT_PAYMENT_WINDOW_HOURS=24

# VodaPay Configuration
VODAPAY_ENABLED=true
VODAPAY_MERCHANT_ID=your_merchant_id
VODAPAY_API_KEY=your_api_key
VODAPAY_TEST_MODE=true
VODAPAY_CALLBACK_URL=/payment/callback/vodapay

# SnapScan Configuration
SNAPSCAN_ENABLED=true
SNAPSCAN_MERCHANT_ID=your_merchant_id
SNAPSCAN_API_KEY=your_api_key
SNAPSCAN_TEST_MODE=true
SNAPSCAN_CALLBACK_URL=/payment/callback/snapscan

# Global Settings
PAYMENT_CURRENCY=ZAR
PAYMENT_TIMEZONE=Africa/Johannesburg
PAYMENT_DECIMAL_PLACES=2
PAYMENT_MINIMUM_AMOUNT=1.00
PAYMENT_MAXIMUM_AMOUNT=1000000.00
PAYMENT_DEFAULT_DESCRIPTION=Payment

# Webhook Settings
PAYMENT_WEBHOOKS_ENABLED=true
PAYMENT_WEBHOOK_ROUTE_PREFIX=payment/webhook
PAYMENT_WEBHOOK_SIGNATURE_VERIFICATION=true
PAYMENT_WEBHOOK_QUEUE=default
PAYMENT_WEBHOOK_TIMEOUT=30

# Security Settings
PAYMENT_RATE_LIMIT=60
PAYMENT_RATE_LIMIT_PERIOD=1
PAYMENT_IP_WHITELIST=
PAYMENT_REQUIRE_HTTPS=true
PAYMENT_ENCRYPT_SENSITIVE_DATA=true

# Notification Settings
PAYMENT_EMAIL_NOTIFICATIONS=true
PAYMENT_SENDER_EMAIL=noreply@example.com
PAYMENT_SENDER_NAME="Payment System"
PAYMENT_SMS_NOTIFICATIONS=false
PAYMENT_SMS_PROVIDER=twilio
PAYMENT_SLACK_NOTIFICATIONS=false
PAYMENT_SLACK_WEBHOOK_URL=

# Analytics Settings
PAYMENT_ANALYTICS_ENABLED=true
PAYMENT_ANALYTICS_RETENTION_DAYS=365
PAYMENT_DASHBOARD_ENABLED=true
PAYMENT_EXPORT_ENABLED=true

# Recurring Payments
PAYMENT_RECURRING_ENABLED=true
PAYMENT_GRACE_PERIOD_DAYS=3
PAYMENT_RETRY_ATTEMPTS=3
PAYMENT_RETRY_INTERVAL_HOURS=24

# Logging
PAYMENT_LOGGING_ENABLED=true
PAYMENT_LOG_LEVEL=info
PAYMENT_LOG_CHANNEL=stack
PAYMENT_SENSITIVE_DATA_MASKING=true
EOF

    print_success ".env.example file created"
}

main() {
    check_dependencies
    validate_package

    echo ""
    print_header
    echo "Publication Steps:"
    echo "1. Prepare Git repository"
    echo "2. Create GitHub repository"
    echo "3. Setup GitHub Actions"
    echo "4. Setup Packagist"
    echo "5. Additional configuration"
    echo ""

    # Step 1: Prepare Git
    read -p "Run Step 1: Prepare Git repository? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        prepare_git_repository
        create_composer_scripts
        generate_env_example
    fi

    # Step 2: GitHub Repository
    echo ""
    read -p "Show Step 2: GitHub repository setup instructions? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        create_github_repository
    fi

    # Step 3: GitHub Actions
    echo ""
    read -p "Run Step 3: Setup GitHub Actions? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        setup_github_actions
    fi

    # Step 4: Packagist
    echo ""
    read -p "Show Step 4: Packagist setup instructions? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        setup_packagist
    fi

    # Final instructions
    echo ""
    print_header
    print_success "Package preparation complete!"
    echo ""
    echo "Next steps:"
    echo "1. Push to GitHub:"
    echo "   git remote add origin ${REPO_URL}"
    echo "   git push -u origin main --tags"
    echo ""
    echo "2. Submit to Packagist:"
    echo "   Visit https://packagist.org/packages/submit"
    echo ""
    echo "3. Announce the release:"
    echo "   - Update documentation"
    echo "   - Share on social media"
    echo "   - Submit to Laravel News"
    echo ""
    echo "4. Monitor and support:"
    echo "   - Watch GitHub issues"
    echo "   - Respond to questions"
    echo "   - Gather feedback for improvements"
}

# Run main function
main "$@"
