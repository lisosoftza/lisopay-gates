@echo off
REM Lisosoft Laravel Payment Gateway - Package Publication Helper (Windows)
REM This batch file helps with publishing the package to GitHub and Packagist

setlocal enabledelayedexpansion

REM Configuration
set PACKAGE_NAME=lisosoft/laravel-payment-gateway
set PACKAGE_VERSION=1.0.0
set REPO_URL=https://github.com/lisosoft/laravel-payment-gateway.git

REM Colors (Windows 10+)
for /F %%a in ('echo prompt $E ^| cmd') do set "ESC=%%a"
set "GREEN=%ESC%[32m"
set "RED=%ESC%[31m"
set "YELLOW=%ESC%[33m"
set "BLUE=%ESC%[34m"
set "NC=%ESC%[0m"

REM Functions
:print_header
echo %BLUE%
echo ========================================
echo Lisosoft Payment Gateway Publication
echo ========================================
echo %NC%
goto :eof

:print_success
echo %GREEN%✓ %1%NC%
goto :eof

:print_error
echo %RED%✗ %1%NC%
goto :eof

:print_warning
echo %YELLOW%⚠ %1%NC%
goto :eof

:print_info
echo %BLUE%ℹ %1%NC%
goto :eof

:check_dependencies
call :print_header
call :print_info "Checking dependencies..."

REM Check for required commands
set missing_deps=
where git >nul 2>nul || set missing_deps=!missing_deps! git
where composer >nul 2>nul || set missing_deps=!missing_deps! composer
where php >nul 2>nul || set missing_deps=!missing_deps! php
where curl >nul 2>nul || set missing_deps=!missing_deps! curl

if not "!missing_deps!"=="" (
    call :print_error "Missing dependencies:!missing_deps!"
    exit /b 1
)

call :print_success "All dependencies found"
goto :eof

:validate_package
call :print_info "Validating package structure..."

REM Check required files
set required_files=composer.json README.md LICENSE CHANGELOG.md src/PaymentGatewayServiceProvider.php config/payment-gateway.php
set all_files_exist=true

for %%f in (%required_files%) do (
    if not exist "%%f" (
        call :print_error "Missing required file: %%f"
        set all_files_exist=false
    )
)

if "!all_files_exist!"=="false" exit /b 1

REM Validate composer.json
php -r "json_decode(file_get_contents('composer.json'), true); if (json_last_error() !== JSON_ERROR_NONE) { exit(1); }" >nul 2>nul
if errorlevel 1 (
    call :print_error "Invalid composer.json"
    exit /b 1
)

REM Check PHP syntax
call :print_info "Checking PHP syntax..."
for /r src %%f in (*.php) do (
    php -l "%%f" >nul 2>nul
    if errorlevel 1 (
        call :print_error "PHP syntax error in %%f"
        exit /b 1
    )
)

call :print_success "Package validation passed"
goto :eof

:prepare_git_repository
call :print_info "Preparing Git repository..."

REM Initialize git if not already
if not exist ".git" (
    git init
    call :print_success "Git repository initialized"
)

REM Add all files
git add .

REM Check if there are changes
git diff --cached --quiet >nul 2>nul
if errorlevel 1 (
    git commit -m "Release v%PACKAGE_VERSION%"
    call :print_success "Changes committed"
) else (
    call :print_warning "No changes to commit"
)

REM Create version tag
git rev-parse "v%PACKAGE_VERSION%" >nul 2>nul
if errorlevel 1 (
    git tag -a "v%PACKAGE_VERSION%" -m "Version %PACKAGE_VERSION%"
    call :print_success "Tag v%PACKAGE_VERSION% created"
) else (
    call :print_warning "Tag v%PACKAGE_VERSION% already exists"
)
goto :eof

:create_github_repository
call :print_info "GitHub Repository Setup"
echo.
echo To create a GitHub repository:
echo 1. Go to https://github.com/new
echo 2. Repository name: laravel-payment-gateway
echo 3. Description: A comprehensive payment gateway package for Laravel with support for multiple payment providers
echo 4. Visibility: Public
echo 5. DO NOT initialize with README, .gitignore, or license
echo 6. Click 'Create repository'
echo.
echo After creating the repository, run the commands shown on GitHub:
echo.
echo Or if you have the repository URL, you can run:
echo   git remote add origin %REPO_URL%
echo   git push -u origin main --tags
echo.
pause
goto :eof

:setup_github_actions
call :print_info "Setting up GitHub Actions..."

REM Create workflows directory
if not exist ".github\workflows" mkdir ".github\workflows"

REM Create CI workflow
(
echo name: CI
echo.
echo on:
echo   push:
echo     branches: [ main, develop ]
echo   pull_request:
echo     branches: [ main ]
echo.
echo jobs:
echo   test:
echo     runs-on: ubuntu-latest
echo.
echo     strategy:
echo       matrix:
echo         php: [8.1, 8.2, 8.3]
echo         laravel: [10.*, 11.*]
echo.
echo     steps:
echo     - uses: actions/checkout@v4
echo.
echo     - name: Setup PHP
echo       uses: shivammathur/setup-php@v2
echo       with:
echo         php-version: ${{ matrix.php }}
echo         extensions: mbstring, xml, ctype, json, openssl, pdo, tokenizer
echo         coverage: none
echo.
echo     - name: Validate composer.json
echo       run: composer validate --strict
echo.
echo     - name: Cache Composer packages
echo       id: composer-cache
echo       uses: actions/cache@v3
echo       with:
echo         path: vendor
echo         key: ${{ runner.os }}-php-${{ matrix.php }}-${{ hashFiles('**/composer.json') }}
echo         restore-keys: ^|
echo           ${{ runner.os }}-php-${{ matrix.php }}-
echo.
echo     - name: Install dependencies
echo       run: ^|
echo         composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update
echo         composer install --prefer-dist --no-progress
echo.
echo     - name: Run tests
echo       run: vendor/bin/phpunit
echo.
echo     - name: Check code style
echo       run: ^|
echo         composer require --dev friendsofphp/php-cs-fixer
echo         vendor/bin/php-cs-fixer fix --dry-run --diff
echo.
echo   security:
echo     runs-on: ubuntu-latest
echo.
echo     steps:
echo     - uses: actions/checkout@v4
echo.
echo     - name: Security check
echo       run: ^|
echo         composer require --dev enlightn/security-checker
echo         vendor/bin/security-checker security:check composer.lock
) > .github\workflows\ci.yml

REM Create release workflow
(
echo name: Release
echo.
echo on:
echo   push:
echo     tags:
echo       - 'v*'
echo.
echo jobs:
echo   release:
echo     runs-on: ubuntu-latest
echo.
echo     steps:
echo     - uses: actions/checkout@v4
echo.
echo     - name: Setup PHP
echo       uses: shivammathur/setup-php@v2
echo       with:
echo         php-version: '8.1'
echo         extensions: mbstring, xml, ctype, json
echo.
echo     - name: Validate composer.json
echo       run: composer validate --strict
echo.
echo     - name: Get version
echo       id: get_version
echo       run: echo "VERSION=${GITHUB_REF#refs/tags/}" ^>^> $GITHUB_OUTPUT
echo.
echo     - name: Create GitHub Release
echo       uses: softprops/action-gh-release@v1
echo       with:
echo         name: Release ${{ steps.get_version.outputs.VERSION }}
echo         draft: false
echo         prerelease: false
echo         generate_release_notes: true
echo       env:
echo         GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
) > .github\workflows\release.yml

git add .github\ >nul 2>nul
git commit -m "Add GitHub Actions workflows" >nul 2>nul || echo.
call :print_success "GitHub Actions workflows created"
goto :eof

:setup_packagist
call :print_info "Packagist Setup Instructions"
echo.
echo To publish to Packagist:
echo.
echo 1. Go to https://packagist.org/login/
echo 2. Log in with your GitHub account
echo 3. Click 'Submit' in the top navigation
echo 4. Enter the repository URL: %REPO_URL%
echo 5. Click 'Check' then 'Submit'
echo.
echo After submission:
echo 1. Go to your package page
echo 2. Click 'Settings'
echo 3. Enable 'Auto-Update'
echo 4. Select 'GitHub Service Hook'
echo 5. Save changes
echo.
pause
goto :eof

:create_composer_scripts
call :print_info "Adding Composer scripts..."

REM Check if jq is available
where jq >nul 2>nul
if errorlevel 1 (
    call :print_warning "jq not found, skipping Composer scripts update"
    goto :eof
)

REM Read current composer.json and add scripts
jq '. + {
    "scripts": {
        "test": "vendor/bin/phpunit",
        "test-coverage": "vendor/bin/phpunit --coverage-html coverage",
        "lint": "php-cs-fixer fix --dry-run --diff",
        "format": "php-cs-fixer fix",
        "analyze": "phpstan analyse src --level=5"
    }
}' composer.json > composer.json.new

move /y composer.json.new composer.json >nul
call :print_success "Composer scripts added"
goto :eof

:generate_env_example
call :print_info "Generating .env.example file..."

(
echo # Payment Gateway Configuration
echo.
echo # Default Gateway
echo PAYMENT_GATEWAY_DEFAULT=payfast
echo.
echo # PayFast Configuration
echo PAYFAST_ENABLED=true
echo PAYFAST_MERCHANT_ID=your_merchant_id
echo PAYFAST_MERCHANT_KEY=your_merchant_key
echo PAYFAST_PASSPHRASE=your_passphrase
echo PAYFAST_TEST_MODE=true
echo PAYFAST_RETURN_URL=/payment/success
echo PAYFAST_CANCEL_URL=/payment/cancel
echo PAYFAST_NOTIFY_URL=/payment/webhook/payfast
echo.
echo # PayStack Configuration
echo PAYSTACK_ENABLED=true
echo PAYSTACK_PUBLIC_KEY=your_public_key
echo PAYSTACK_SECRET_KEY=your_secret_key
echo PAYSTACK_MERCHANT_EMAIL=your_email
echo PAYSTACK_TEST_MODE=true
echo PAYSTACK_CALLBACK_URL=/payment/callback/paystack
echo.
echo # PayPal Configuration
echo PAYPAL_ENABLED=true
echo PAYPAL_CLIENT_ID=your_client_id
echo PAYPAL_CLIENT_SECRET=your_client_secret
echo PAYPAL_MODE=sandbox
echo PAYPAL_RETURN_URL=/payment/success
echo PAYPAL_CANCEL_URL=/payment/cancel
echo PAYPAL_WEBHOOK_ID=your_webhook_id
echo.
echo # Stripe Configuration
echo STRIPE_ENABLED=true
echo STRIPE_PUBLISHABLE_KEY=your_publishable_key
echo STRIPE_SECRET_KEY=your_secret_key
echo STRIPE_WEBHOOK_SECRET=your_webhook_secret
echo STRIPE_TEST_MODE=true
echo STRIPE_RETURN_URL=/payment/success
echo.
echo # Ozow Configuration
echo OZOW_ENABLED=true
echo OZOW_SITE_CODE=your_site_code
echo OZOW_PRIVATE_KEY=your_private_key
echo OZOW_API_KEY=your_api_key
echo OZOW_TEST_MODE=true
echo OZOW_CALLBACK_URL=/payment/callback/ozow
echo OZOW_ERROR_URL=/payment/error
echo.
echo # Zapper Configuration
echo ZAPPER_ENABLED=true
echo ZAPPER_MERCHANT_ID=your_merchant_id
echo ZAPPER_SITE_ID=your_site_id
echo ZAPPER_API_KEY=your_api_key
echo ZAPPER_TEST_MODE=true
echo ZAPPER_CALLBACK_URL=/payment/callback/zapper
echo.
echo # Cryptocurrency Configuration
echo CRYPTO_ENABLED=true
echo CRYPTO_PROVIDER=coinbase
echo CRYPTO_API_KEY=your_api_key
echo CRYPTO_API_SECRET=your_api_secret
echo CRYPTO_WEBHOOK_SECRET=your_webhook_secret
echo.
echo # EFT/Bank Transfer Configuration
echo EFT_ENABLED=true
echo EFT_BANK_NAME="Standard Bank"
echo EFT_ACCOUNT_NAME="Your Business Name"
echo EFT_ACCOUNT_NUMBER=1234567890
echo EFT_BRANCH_CODE=051001
echo EFT_REFERENCE_PREFIX=LISO
echo EFT_PAYMENT_WINDOW_HOURS=24
echo.
echo # VodaPay Configuration
echo VODAPAY_ENABLED=true
echo VODAPAY_MERCHANT_ID=your_merchant_id
echo VODAPAY_API_KEY=your_api_key
echo VODAPAY_TEST_MODE=true
echo VODAPAY_CALLBACK_URL=/payment/callback/vodapay
echo.
echo # SnapScan Configuration
echo SNAPSCAN_ENABLED=true
echo SNAPSCAN_MERCHANT_ID=your_merchant_id
echo SNAPSCAN_API_KEY=your_api_key
echo SNAPSCAN_TEST_MODE=true
echo SNAPSCAN_CALLBACK_URL=/payment/callback/snapscan
echo.
echo # Global Settings
echo PAYMENT_CURRENCY=ZAR
echo PAYMENT_TIMEZONE=Africa/Johannesburg
echo PAYMENT_DECIMAL_PLACES=2
echo PAYMENT_MINIMUM_AMOUNT=1.00
echo PAYMENT_MAXIMUM_AMOUNT=1000000.00
echo PAYMENT_DEFAULT_DESCRIPTION=Payment
echo.
echo # Webhook Settings
echo PAYMENT_WEBHOOKS_ENABLED=true
echo PAYMENT_WEBHOOK_ROUTE_PREFIX=payment/webhook
echo PAYMENT_WEBHOOK_SIGNATURE_VERIFICATION=true
echo PAYMENT_WEBHOOK_QUEUE=default
echo PAYMENT_WEBHOOK_TIMEOUT=30
echo.
echo # Security Settings
echo PAYMENT_RATE_LIMIT=60
echo PAYMENT_RATE_LIMIT_PERIOD=1
echo PAYMENT_IP_WHITELIST=
echo PAYMENT_REQUIRE_HTTPS=true
echo PAYMENT_ENCRYPT_SENSITIVE_DATA=true
echo.
echo # Notification Settings
echo PAYMENT_EMAIL_NOTIFICATIONS=true
echo PAYMENT_SENDER_EMAIL=noreply@example.com
echo PAYMENT_SENDER_NAME="Payment System"
echo PAYMENT_SMS_NOTIFICATIONS=false
echo PAYMENT_SMS_PROVIDER=twilio
echo PAYMENT_SLACK_NOTIFICATIONS=false
echo PAYMENT_SLACK_WEBHOOK_URL=
echo.
echo # Analytics Settings
echo PAYMENT_ANALYTICS_ENABLED=true
echo PAYMENT_ANALYTICS_RETENTION_DAYS=365
echo PAYMENT_DASHBOARD_ENABLED=true
echo PAYMENT_EXPORT_ENABLED=true
echo.
echo # Recurring Payments
echo PAYMENT_RECURRING_ENABLED=true
echo PAYMENT_GRACE_PERIOD_DAYS=3
echo PAYMENT_RETRY_ATTEMPTS=3
echo PAYMENT_RETRY_INTERVAL_HOURS=24
echo.
echo # Logging
echo PAYMENT_LOGGING_ENABLED=true
echo PAYMENT_LOG_LEVEL=info
echo PAYMENT_LOG_CHANNEL=stack
echo PAYMENT_SENSITIVE_DATA_MASKING=true
) > .env.example

call :print_success ".env.example file created"
goto :eof

:main
call :check_dependencies
call :validate_package

echo.
call :print_header
echo Publication Steps:
echo 1. Prepare Git repository
echo 2. Create GitHub repository
echo 3. Setup GitHub Actions
echo 4. Setup Packagist
echo 5. Additional configuration
echo.

REM Step 1: Prepare Git
set /p step1="Run Step 1: Prepare Git repository? (y/n): "
if /i "!step1!"=="y" (
    call :prepare_git_repository
    call :create_composer_scripts
    call :generate_env_example
)

REM Step 2: GitHub Repository
echo.
set /p step2="Show Step 2: GitHub repository setup instructions? (y/n): "
if /i "!step2!"=="y" (
    call :create_github_repository
)

REM Step 3: GitHub Actions
echo.
set /p step3="Run Step 3: Setup GitHub Actions? (y/n): "
if /i "!step3!"=="y" (
    call :setup_github_actions
)

REM Step 4: Packagist
echo.
set /p step4="Show Step 4: Packagist setup instructions? (y/n): "
if /i "!step4!"=="y" (
    call :setup_packagist
)

REM Final instructions
echo.
call :print_header
call :print_success "Package preparation complete!"
echo.
echo Next steps:
echo 1. Push to GitHub:
echo    git remote add origin %REPO_URL%
echo    git push -u origin main --tags
echo.
echo 2. Submit to Packagist:
echo    Visit https://packagist.org/packages/submit
echo.
echo 3. Announce the release:
echo    - Update documentation
echo    - Share on social media
echo    - Submit to Laravel News
echo.
echo 4. Monitor and support:
echo    - Watch GitHub issues
echo    - Respond to questions
echo    - Gather feedback for improvements
echo.
pause
goto :eof

REM Run main function
call :main
endlocal
