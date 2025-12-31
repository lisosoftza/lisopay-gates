# Lisosoft Payment Gateway - Deployment Guide

## Table of Contents
1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Production Environment Setup](#production-environment-setup)
3. [Database Configuration](#database-configuration)
4. [Payment Gateway Configuration](#payment-gateway-configuration)
5. [Security Configuration](#security-configuration)
6. [Performance Optimization](#performance-optimization)
7. [Monitoring & Logging](#monitoring--logging)
8. [Backup Strategy](#backup-strategy)
9. [Scaling Considerations](#scaling-considerations)
10. [Disaster Recovery](#disaster-recovery)
11. [Maintenance Procedures](#maintenance-procedures)

## Pre-Deployment Checklist

### Environment Verification
- [ ] PHP 8.1+ installed and configured
- [ ] Laravel 10.x or 11.x installed
- [ ] Composer 2.0+ installed
- [ ] Database server (MySQL 5.7+/PostgreSQL 9.5+) installed
- [ ] Redis/Memcached installed (recommended for caching)
- [ ] Queue worker configured (Supervisor/Systemd)
- [ ] SSL certificate installed and configured
- [ ] Domain name configured with DNS

### Application Verification
- [ ] All tests passing (`composer test`)
- [ ] Code quality checks passed (`composer lint`)
- [ ] Security audit completed
- [ ] Performance testing completed
- [ ] Backup system configured
- [ ] Monitoring tools installed
- [ ] Logging system configured

### Payment Gateway Verification
- [ ] Production API keys obtained for all gateways
- [ ] Webhook URLs configured
- [ ] IP whitelisting configured (if required)
- [ ] Gateway-specific compliance requirements met
- [ ] Test transactions completed in sandbox
- [ ] Settlement accounts verified

## Production Environment Setup

### Server Requirements

#### Minimum Requirements
- **CPU**: 2+ cores
- **RAM**: 4GB minimum, 8GB recommended
- **Storage**: 50GB SSD
- **Bandwidth**: 100GB/month minimum
- **Operating System**: Ubuntu 20.04 LTS or higher

#### Recommended Production Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2 with extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-pgsql \
    php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip \
    php8.2-bcmath php8.2-intl php8.2-redis php8.2-sqlite3

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js (for asset compilation)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Redis
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Install Supervisor
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### Nginx Configuration

Create `/etc/nginx/sites-available/your-domain.com`:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    ssl_certificate /etc/ssl/certs/your-domain.com.crt;
    ssl_certificate_key /etc/ssl/private/your-domain.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    root /var/www/your-domain.com/public;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;

    # Payment gateway specific
    location ~ ^/payment/webhook/ {
        client_max_body_size 10M;
        client_body_buffer_size 128k;
        proxy_read_timeout 90;
        proxy_connect_timeout 90;
        proxy_send_timeout 90;
    }

    # Static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Logging
    access_log /var/log/nginx/your-domain.com.access.log;
    error_log /var/log/nginx/your-domain.com.error.log;
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/your-domain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Supervisor Configuration

Create `/etc/supervisor/conf.d/laravel-worker.conf`:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/your-domain.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/www/your-domain.com/storage/logs/worker.log
stopwaitsecs=3600
```

Create `/etc/supervisor/conf.d/laravel-scheduler.conf`:
```ini
[program:laravel-scheduler]
process_name=%(program_name)s_%(process_num)02d
command=/bin/bash -c "while [ true ]; do (php /var/www/your-domain.com/artisan schedule:run --verbose --no-interaction &); sleep 60; done"
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/your-domain.com/storage/logs/scheduler.log
user=www-data
```

Reload Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

## Database Configuration

### Production Database Setup

#### MySQL Configuration
```sql
-- Create database and user
CREATE DATABASE payment_gateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'payment_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON payment_gateway.* TO 'payment_user'@'localhost';
FLUSH PRIVILEGES;

-- Optimize configuration
SET GLOBAL innodb_buffer_pool_size = 2G;
SET GLOBAL innodb_log_file_size = 256M;
SET GLOBAL max_connections = 200;
SET GLOBAL query_cache_size = 128M;
```

#### PostgreSQL Configuration
```sql
-- Create database and user
CREATE DATABASE payment_gateway;
CREATE USER payment_user WITH PASSWORD 'strong_password_here';
GRANT ALL PRIVILEGES ON DATABASE payment_gateway TO payment_user;

-- Optimize configuration
ALTER SYSTEM SET shared_buffers = '2GB';
ALTER SYSTEM SET effective_cache_size = '6GB';
ALTER SYSTEM SET maintenance_work_mem = '512MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
ALTER SYSTEM SET default_statistics_target = 100;
```

### Database Connection Pooling

Install and configure PgBouncer (PostgreSQL) or ProxySQL (MySQL):

```ini
# PgBouncer configuration (/etc/pgbouncer/pgbouncer.ini)
[databases]
payment_gateway = host=localhost port=5432 dbname=payment_gateway

[pgbouncer]
listen_addr = *
listen_port = 6432
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 20
```

### Database Backups

Configure automated backups:

```bash
#!/bin/bash
# /usr/local/bin/backup-database.sh

BACKUP_DIR="/backups/database"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="payment_gateway"

# MySQL backup
mysqldump --single-transaction --quick --lock-tables=false \
    -u payment_user -p'password' $DB_NAME | gzip > $BACKUP_DIR/$DB_NAME_$DATE.sql.gz

# Keep backups for 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

# Sync to remote storage (optional)
aws s3 sync $BACKUP_DIR s3://your-bucket/database-backups/
```

Add to crontab:
```bash
0 2 * * * /usr/local/bin/backup-database.sh
```

## Payment Gateway Configuration

### Production Gateway Settings

Update `.env` with production credentials:

```env
# PayFast Production
PAYFAST_TEST_MODE=false
PAYFAST_MERCHANT_ID=your_production_merchant_id
PAYFAST_MERCHANT_KEY=your_production_merchant_key
PAYFAST_PASSPHRASE=your_production_passphrase

# PayStack Production
PAYSTACK_TEST_MODE=false
PAYSTACK_PUBLIC_KEY=pk_live_xxxxxxxxxxxxx
PAYSTACK_SECRET_KEY=sk_live_xxxxxxxxxxxxx

# PayPal Production
PAYPAL_MODE=live
PAYPAL_CLIENT_ID=your_production_client_id
PAYPAL_CLIENT_SECRET=your_production_client_secret

# Stripe Production
STRIPE_TEST_MODE=false
STRIPE_PUBLISHABLE_KEY=pk_live_xxxxxxxxxxxxx
STRIPE_SECRET_KEY=sk_live_xxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx

# Other gateways...
```

### Webhook Configuration

#### Production Webhook URLs
```env
PAYFAST_NOTIFY_URL=https://your-domain.com/payment/webhook/payfast
PAYSTACK_CALLBACK_URL=https://your-domain.com/payment/callback/paystack
PAYPAL_WEBHOOK_URL=https://your-domain.com/payment/webhook/paypal
STRIPE_WEBHOOK_URL=https://your-domain.com/payment/webhook/stripe
```

#### Webhook Security
1. **Enable signature verification** for all gateways
2. **Configure IP whitelisting** where supported
3. **Use HTTPS only** for webhook endpoints
4. **Implement retry logic** for failed webhooks
5. **Monitor webhook delivery** with logging

### Gateway-Specific Production Requirements

#### PayFast
- Register for PayFast ITN (Instant Transaction Notification)
- Configure ITN settings in PayFast dashboard
- Set up IPN (Instant Payment Notification) if needed
- Configure settlement bank account

#### PayStack
- Complete KYC verification
- Configure webhook in PayStack dashboard
- Set up transfer recipients for payouts
- Configure settlement schedule

#### PayPal
- Create live app in PayPal Developer dashboard
- Configure webhooks in PayPal dashboard
- Set up payout preferences
- Complete business verification

#### Stripe
- Complete Stripe onboarding
- Configure webhook endpoints in Stripe dashboard
- Set up payout schedule
- Configure Radar rules for fraud detection

## Security Configuration

### Application Security

#### Environment Security
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Encryption
APP_KEY=base64:your_32_character_key_here

# Session security
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# CSRF protection
CSRF_COOKIE_SECURE=true
CSRF_COOKIE_HTTP_ONLY=true
```

#### Database Encryption
Enable encryption for sensitive data:
```php
// In config/payment-gateway.php
'security' => [
    'encrypt_sensitive_data' => true,
    'encryption_key' => env('PAYMENT_ENCRYPTION_KEY'),
],
```

Generate encryption key:
```bash
php artisan payment:generate-encryption-key
```

#### API Security
```env
# API rate limiting
PAYMENT_RATE_LIMIT=100
PAYMENT_RATE_LIMIT_PERIOD=1

# API key authentication
PAYMENT_API_KEY_EXPIRY_DAYS=90
PAYMENT_API_KEY_ROTATION_ENABLED=true
```

### Server Security

#### Firewall Configuration
```bash
# Configure UFW firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

#### SSH Security
```bash
# Disable root login
sudo sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config

# Use key-based authentication
sudo sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config

# Change SSH port (optional)
sudo sed -i 's/#Port 22/Port 2222/' /etc/ssh/sshd_config

sudo systemctl restart sshd
```

#### File Permissions
```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/your-domain.com
sudo chmod -R 755 /var/www/your-domain.com/storage
sudo chmod -R 755 /var/www/your-domain.com/bootstrap/cache

# Protect sensitive files
sudo chmod 600 /var/www/your-domain.com/.env
sudo chmod 600 /var/www/your-domain.com/storage/oauth-*.key
```

### PCI DSS Compliance

#### Level 4 Merchant Requirements
1. **Use compliant hosting provider**
2. **Implement SSL/TLS encryption**
3. **Regular security scans**
4. **Vulnerability management**
5. **Access control measures**
6. **Network security controls**
7. **Regular security testing**
8. **Security policy maintenance**

#### Recommended Tools
- **Qualys SSL Labs** for SSL testing
- **Nessus** for vulnerability scanning
- **OWASP ZAP** for security testing
- **Fail2ban** for intrusion prevention

## Performance Optimization

### Caching Configuration

#### Redis Configuration
```env
CACHE_DRIVER=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### Optimize Redis
```bash
# Edit /etc/redis/redis.conf
sudo nano /etc/redis/redis.conf

# Recommended settings
maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### PHP Optimization

#### PHP-FPM Configuration
```ini
; /etc/php/8.2/fpm/php.ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

#### PHP-FPM Pool Configuration
```ini
; /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

### Database Optimization

#### Index Optimization
```sql
-- Add missing indexes for performance
CREATE INDEX idx_transactions_composite ON payment_transactions (status, created_at);
CREATE INDEX idx_subscriptions_next_billing ON payment_subscriptions (next_billing_date, status);
CREATE INDEX idx_webhooks_processed ON payment_webhook_events (processed, created_at);

-- Regular maintenance
ANALYZE TABLE payment_transactions;
OPTIMIZE TABLE payment_transactions;
```

#### Query Optimization
```php
// Use eager loading
Transaction::with(['customer', 'gateway'])
    ->where('status', 'completed')
    ->whereBetween('created_at', [$startDate, $endDate])
    ->paginate(50);

// Use database transactions
DB::transaction(function () {
    // Payment processing logic
});
```

### CDN Configuration

#### CloudFront Configuration (AWS)
```json
{
    "CacheBehavior": {
        "TargetOriginId": "your-domain.com",
        "ViewerProtocolPolicy": "redirect-to-https",
        "AllowedMethods": ["GET", "HEAD", "OPTIONS"],
        "CachedMethods": ["GET", "HEAD"],
        "CachePolicyId": "658327ea-f89d-4fab-a63d-7e88639e58f6",
        "OriginRequestPolicyId": "88a5eaf4-2fd4-4709-b370-b4c650ea3fcf"
    }
}
```

#### Cloudflare Configuration
1. Enable **Always Use HTTPS**
2. Configure **SSL/TLS** to Full (strict)
3. Enable **Auto Minify** for CSS, JS, HTML
4. Configure **Browser Cache TTL**
5. Enable **Rocket Loader**

## Monitoring & Logging

### Application Monitoring

#### Laravel Telescope (Development/Staging)
```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

#### Production Monitoring Stack
```bash
# Install Prometheus
wget https://github.com/prometheus/prometheus/releases/download/v2.45.0/prometheus-2.45.0.linux-amd64.tar.gz
tar xvfz prometheus-2.45.0.linux-amd64.tar.gz
cd prometheus-2.45.0.linux-amd64

# Install Grafana
sudo apt-get install -y adduser libfontconfig1
wget https://dl.grafana.com/oss/release/grafana_10.0.3_amd64.deb
sudo dpkg -i grafana_10.0.3_amd64.deb
```

### Logging Configuration

#### Structured Logging
```php
// config/logging.php
'channels' => [
    'payment' => [
        'driver' => 'daily',
        'path' => storage_path('logs/payment-gateway.log'),
        'level' => 'info',
        'days' => 30,
        'formatter' => Monolog\Formatter\JsonFormatter::class,
    ],
    
    'webhook' => [
        'driver' => 'daily',
        'path' => storage_path('logs/webhook.log'),
        'level' => 'debug',
        'days' => 7,
    ],
],
```

#### Log Rotation
```bash
# /etc/logrotate.d/laravel
/var/www/your-domain.com/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 640 www-data www-data
    sharedscripts
    postrotate
        kill -USR1 `cat /var/run/php8.2-fpm.pid 2>/dev/null` 2>/dev/null || true
    endscript
}
```

### Alerting Configuration

#### Payment Failure Alerts
```php
// App\Listeners\PaymentFailedListener.php
public function handle(PaymentFailed $event)
{
    // Send email alert
    Mail::to(config('payment.notifications.admin_email'))
        ->send(new PaymentFailedAlert($event->transaction));
    
    // Send Slack alert
    if (config('payment.notifications.slack.enabled')) {
        Slack::to(config('payment.notifications.slack.channel'))
            ->send(new PaymentFailedSlackMessage($event->transaction));
    }
    
    // Log to monitoring system
    Log::channel('monitoring')->error('Payment failed', [
        'transaction_id' => $event->transaction->id,
        'amount' => $event->transaction->amount,
        'gateway' => $event->transaction->gateway,
    ]);
}
```

#### System Health Checks
```bash
#!/bin/bash
# /usr/local/bin/health-check.sh

# Check database connection
if ! mysql -u payment_user -p'password' -e "SELECT 1" payment_gateway; then
    echo "Database connection failed" | mail -s "Payment System Alert" admin@example.com
fi

# Check Redis connection
if ! redis-cli ping | grep -q PONG; then
    echo "Redis connection failed" | mail -s "Payment System Alert" admin@example.com
fi

# Check queue workers
if ! supervisorctl status laravel-worker | grep -q RUNNING; then
    echo "Queue workers down" | mail -s "Payment System Alert" admin@example.com
fi

# Check disk space
DISK_USAGE=$(df / | awk 'END{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    echo "Disk usage critical: ${DISK_USAGE}%" | mail -s "Payment System Alert" admin@example.com
fi
```

## Backup Strategy

### Backup Components

#### 1. Database Backups
```bash
# Daily full backup
0 2 * * * /usr/local/bin/backup-database.sh

# Hourly incremental backup (if needed)
0 * * * * /usr/local/bin/backup-database-incremental.sh
```

#### 2. File Backups
```bash
# Backup application code
0 3 * * * tar -czf /backups/code/$(date +\%Y\%m\%d).tar.gz /var/www/your-domain.com

# Backup uploaded files
0 4 * * * tar -czf /backups/uploads/$(date +\%Y\%m\%d).tar.gz /var/www/your-domain.com/storage/app/public
```

#### 3. Configuration Backups
```bash
# Backup configuration files
0 5 * * * tar -czf /backups/config/$(date +\%Y\%m\%d).tar.gz \
    /etc/nginx/sites-available/your-domain.com \
    /etc/php/8.2/fpm/php.ini \
    /etc/redis/redis.conf \
    /etc/supervisor/conf.d/
```

### Backup Storage

#### Local Storage
```bash
# Configure backup directory
sudo mkdir -p /backups/{database,code,uploads,config}
sudo chown -R www-data:www-data /backups
sudo chmod -R 750 /backups
```

#### Remote Storage (AWS S3)
```bash
# Install AWS CLI
sudo apt install -y awscli

# Configure backup script
#!/bin/bash
BACKUP_FILE="/backups/database/$(date +%Y%m%d).sql.gz"
aws s3 cp $BACKUP_FILE s3://your-bucket/database-backups/
aws s3 cp /backups/code/$(date +%Y%m%d).tar.gz s3://your-bucket/code-backups/
```

#### Backup Retention Policy
- **Daily backups**: Keep for 30 days
- **Weekly backups**: Keep for 12 weeks
- **Monthly backups**: Keep for 12 months
- **Yearly backups**: Keep indefinitely

### Backup Verification

#### Automated Verification
```bash
#!/bin/bash
# /usr/local/bin/verify-backup.sh

# Verify database backup
if ! gunzip -t /backups/database/$(date +%Y%m%d).sql.gz; then
    echo "Database backup verification failed" | mail -s "Backup Alert" admin@example.com
fi

# Verify file integrity
if ! tar -tzf /backups/code/$(date +%Y%m%d).tar.gz >/dev/null 2>&1; then
    echo "Code backup verification failed" | mail -s "Backup Alert" admin@example.com
fi

# Test restore (monthly)
if [ $(date +%d) -eq 1 ]; then
    # Test database restore
    gunzip -c /backups/database/$(date +%Y%m%d).sql.gz | mysql -u test_user -p'test_pass' test_db
fi
```

## Scaling Considerations

### Horizontal Scaling

#### Load Balancer Configuration
```nginx
# Nginx load balancer configuration
upstream payment_backend {
    least_conn;
    server 10.0.1.1:80 weight=3;
    server 10.0.1.2:80;
    server 10.0.1.3:80;
    server 10.0.1.4:80 backup;
}

server {
    listen 80;
    server_name your-domain.com;
    
    location / {
        proxy_pass http://payment_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### Session Management
```env
# Use Redis for shared sessions
SESSION_DRIVER=redis
SESSION_CONNECTION=session

# Or use database for sessions
SESSION_DRIVER=database
SESSION_TABLE=sessions
```

### Database Scaling

#### Read Replicas
```env
# Configure read replicas in database.php
'read' => [
    'host' => [
        '10.0.2.1',
        '10.0.2.2',
        '10.0.2.3',
    ]
],
'write' => [
    'host' => '10.0.1.1'
],
```

#### Database Sharding Strategy
```php
// Shard by customer ID or date
class TransactionShard
{
    public static function getConnection($transactionId)
    {
        $shard = $transactionId % 4; // 4 shards
        return "shard_{$shard}";
    }
}
```

### Cache Scaling

#### Redis Cluster
```env
REDIS_CLUSTER=true
REDIS_CLUSTER_NODES=redis://10.0.3.1:6379,redis://10.0.3.2:6379,redis://10.0.3.3:6379
```

#### CDN for Static Assets
```php
// Use CDN for assets
Asset::cdn([
    'css' => 'https://cdn.your-domain.com/css',
    'js' => 'https://cdn.your-domain.com/js',
    'images' => 'https://cdn.your-domain.com/images',
]);
```

## Disaster Recovery

### Recovery Time Objectives (RTO)
- **Critical**: 1 hour (payment processing)
- **High**: 4 hours (customer access)
- **Medium**: 24 hours (reporting/analytics)
- **Low**: 72 hours (historical data)

### Recovery Point Objectives (RPO)
- **Critical**: 5 minutes (transaction data)
- **High**: 1 hour (customer data)
- **Medium**: 24 hours (analytics data)

### Disaster Recovery Plan

#### 1. Immediate Response
```bash
# Activate backup site
aws ec2 start-instances --instance-ids i-1234567890abcdef0

# Update DNS
aws route53 change-resource-record-sets \
    --hosted-zone-id Z1234567890ABC \
    --change-batch file://dns-update.json
```

#### 2. Data Recovery
```bash
# Restore database
gunzip -c /backups/database/latest.sql.gz | mysql -u root -p payment_gateway

# Restore files
tar -xzf /backups/code/latest.tar.gz -C /var/www/your-domain.com
tar -xzf /backups/uploads/latest.tar.gz -C /var/www/your-domain.com/storage/app/public
```

#### 3. Verification
```bash
# Verify application
curl -I https://your-domain.com/health

# Verify payments
php artisan payment:test-transaction --gateway=payfast --amount=1.00

# Verify webhooks
php artisan payment:test-webhook --gateway=payfast
```

### Disaster Recovery Testing

#### Quarterly DR Test
1. **Simulate failure** of primary site
2. **Activate DR site**
3. **Verify functionality**
4. **Measure recovery time**
5. **Document results**
6. **Update procedures**

## Maintenance Procedures

### Regular Maintenance Tasks

#### Daily Tasks
```bash
# Check system health
php artisan payment:status

# Monitor failed jobs
php artisan queue:failed

# Check disk space
df -h

# Review logs
tail -100 /var/www/your-domain.com/storage/logs/laravel.log
```

#### Weekly Tasks
```bash
# Optimize database
php artisan payment:optimize-database

# Clean up old data
php artisan payment:cleanup --days=30

# Backup verification
php artisan payment:verify-backups

# Security scan
php artisan payment:security-scan
```

#### Monthly Tasks
```bash
# Update dependencies
composer update --no-dev
npm update

# Database maintenance
php artisan payment:database-maintenance

# Performance review
php artisan payment:performance-review

# Compliance check
php artisan payment:compliance-check
```

### Update Procedures

#### Minor Updates
```bash
# Backup current state
php artisan backup:run

# Update package
composer update lisosoft/laravel-payment-gateway --no-dev

# Run migrations
php artisan migrate

# Clear cache
php artisan optimize:clear

# Test functionality
php artisan payment:test --all
```

#### Major Updates
1. **Schedule maintenance window**
2. **Notify stakeholders**
3. **Backup everything**
4. **Deploy to staging first**
5. **Run comprehensive tests**
6. **Deploy to production**
7. **Monitor closely**
8. **Rollback plan ready**

### Incident Response

#### Payment Processing Incident
1. **Identify affected transactions**
2. **Pause affected gateway**
3. **Notify customers**
4. **Investigate root cause**
5. **Implement fix**
6. **Resume processing**
7. **Post-mortem analysis**

#### Security Incident
1. **Isolate affected systems**
2. **Preserve evidence**
3. **Notify authorities (if required)**
4. **Assess damage**
5. **Implement remediation**
6. **Notify affected parties**
7. **Review security controls**

---

## Deployment Checklist Summary

### Pre-Deployment
- [ ] All tests passing
- [ ] Security audit completed
- [ ] Performance testing done
- [ ] Backup system configured
- [ ] Monitoring tools installed

### Deployment
- [ ] Environment configured
- [ ] Database optimized
- [ ] Gateways configured
- [ ] Security measures implemented
- [ ] CDN configured

### Post-Deployment
- [ ] Functionality verified
- [ ] Performance monitored
- [ ] Alerts configured
- [ ] Documentation updated
- [ ] Team trained

### Ongoing
- [ ] Regular backups
- [ ] Security updates
- [ ] Performance optimization
- [ ] Compliance checks
- [ ] Disaster recovery testing

---

**Emergency Contacts**
- Technical Support: +27 11 123 4567
- Security Incident: security@lisosoft.com
- Payment Gateway Support: gateways@lisosoft.com
- 24/7 Monitoring: noc@lisosoft.com

**Documentation**
- [API Documentation](API.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Security Guidelines](SECURITY.md)
- [Compliance Documentation](COMPLIANCE.md)

**Last Updated**: January 2024
**Version**: 1.0.0