# Lisosoft Payment Gateway API Documentation

## Table of Contents
1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base URL](#base-url)
4. [Response Format](#response-format)
5. [Error Handling](#error-handling)
6. [Payment Endpoints](#payment-endpoints)
7. [Subscription Endpoints](#subscription-endpoints)
8. [Transaction Endpoints](#transaction-endpoints)
9. [Gateway Endpoints](#gateway-endpoints)
10. [Webhook Endpoints](#webhook-endpoints)
11. [Analytics Endpoints](#analytics-endpoints)
12. [Admin Endpoints](#admin-endpoints)
13. [Rate Limiting](#rate-limiting)
14. [Webhooks](#webhooks)
15. [Testing](#testing)

## Overview

The Lisosoft Payment Gateway API provides a comprehensive interface for processing payments, managing subscriptions, and handling payment-related operations across multiple payment gateways. The API follows RESTful principles and returns JSON responses.

## Authentication

### API Key Authentication
All API requests require an API key passed in the `Authorization` header:

```http
Authorization: Bearer {your_api_key}
```

### Generating API Keys
API keys can be generated through the admin dashboard or via the command line:

```bash
php artisan payment:generate-api-key --name="Production Key"
```

### Scopes and Permissions
API keys support different permission scopes:
- `payments:read` - Read payment information
- `payments:write` - Create and update payments
- `subscriptions:read` - Read subscription information
- `subscriptions:write` - Create and update subscriptions
- `transactions:read` - Read transaction information
- `gateways:read` - Read gateway information
- `gateways:write` - Update gateway configuration
- `admin:all` - Full administrative access

## Base URL

The base URL for the API depends on your environment:

- **Development**: `http://localhost:8000/api`
- **Staging**: `https://staging.example.com/api`
- **Production**: `https://api.example.com/api`

All endpoints are prefixed with `/api/v1/` (version 1).

## Response Format

### Success Response
```json
{
    "success": true,
    "message": "Payment initialized successfully",
    "data": {
        "reference": "PAY-1234567890",
        "payment_url": "https://payfast.co.za/eng/process",
        "transaction_id": 1,
        "amount": 100.00,
        "currency": "ZAR",
        "gateway": "payfast"
    },
    "meta": {
        "version": "1.0",
        "timestamp": "2024-01-15T10:30:00Z",
        "api_version": "v1"
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Invalid payment gateway",
    "errors": {
        "gateway": ["The selected payment gateway is invalid."]
    },
    "code": "VALIDATION_ERROR",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

## Error Handling

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

### Error Codes
- `VALIDATION_ERROR` - Input validation failed
- `GATEWAY_ERROR` - Payment gateway error
- `NOT_FOUND` - Resource not found
- `UNAUTHORIZED` - Authentication required
- `FORBIDDEN` - Insufficient permissions
- `RATE_LIMITED` - Rate limit exceeded
- `SERVER_ERROR` - Internal server error

## Payment Endpoints

### Initialize Payment
Initialize a new payment transaction.

```http
POST /api/payments/initialize
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "amount": 100.00,
    "currency": "ZAR",
    "description": "Product Purchase",
    "customer": {
        "email": "customer@example.com",
        "name": "John Doe",
        "phone": "+27123456789",
        "address": "123 Main St, Johannesburg"
    },
    "metadata": {
        "order_id": 12345,
        "product_id": 67890,
        "user_id": 42
    },
    "return_url": "https://example.com/payment/success",
    "cancel_url": "https://example.com/payment/cancel",
    "webhook_url": "https://example.com/webhooks/payment"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment initialized successfully",
    "data": {
        "reference": "PAY-1234567890",
        "payment_url": "https://payfast.co.za/eng/process",
        "transaction_id": 1,
        "amount": 100.00,
        "currency": "ZAR",
        "gateway": "payfast",
        "expires_at": "2024-01-15T11:30:00Z"
    }
}
```

### Get Payment Status
Get the status of a payment transaction.

```http
GET /api/payments/status/{reference}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment status retrieved",
    "data": {
        "reference": "PAY-1234567890",
        "status": "completed",
        "amount": 100.00,
        "currency": "ZAR",
        "gateway": "payfast",
        "customer": {
            "email": "customer@example.com",
            "name": "John Doe"
        },
        "metadata": {
            "order_id": 12345
        },
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:31:00Z",
        "completed_at": "2024-01-15T10:31:00Z"
    }
}
```

### Verify Payment
Manually verify a payment status with the gateway.

```http
POST /api/payments/verify
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "reference": "PAY-1234567890"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment verified successfully",
    "data": {
        "reference": "PAY-1234567890",
        "status": "completed",
        "gateway_status": "COMPLETE",
        "verified": true,
        "transaction": {
            "id": 1,
            "amount": 100.00,
            "currency": "ZAR"
        }
    }
}
```

### Refund Payment
Refund a completed payment.

```http
POST /api/payments/refund
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "reference": "PAY-1234567890",
    "amount": 50.00,
    "reason": "Customer request",
    "metadata": {
        "refund_reason_code": "CUSTOMER_REQUEST"
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Refund processed successfully",
    "data": {
        "refund_id": "REF-9876543210",
        "reference": "PAY-1234567890",
        "amount": 50.00,
        "status": "refunded",
        "transaction": {
            "id": 1,
            "original_amount": 100.00,
            "refunded_amount": 50.00
        }
    }
}
```

### Retry Failed Payment
Retry a failed payment.

```http
POST /api/payments/retry
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "reference": "PAY-1234567890"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment retry initiated",
    "data": {
        "retry_initiated": true,
        "new_reference": "PAY-9876543210",
        "payment_url": "https://payfast.co.za/eng/process",
        "original_transaction": {
            "reference": "PAY-1234567890",
            "status": "failed"
        }
    }
}
```

### Get Payment Receipt
Get a payment receipt.

```http
GET /api/payments/{reference}/receipt
```

**Query Parameters:**
- `format` - Output format: `json` (default), `pdf`, `html`

**Response:**
```json
{
    "success": true,
    "message": "Receipt generated successfully",
    "data": {
        "receipt": {
            "id": "REC-1234567890",
            "number": "INV-2024-001",
            "date": "2024-01-15T10:31:00Z",
            "amount": 100.00,
            "currency": "ZAR",
            "tax_amount": 15.00,
            "total_amount": 115.00
        },
        "transaction": {
            "reference": "PAY-1234567890",
            "gateway": "payfast",
            "payment_method": "credit_card"
        },
        "customer": {
            "name": "John Doe",
            "email": "customer@example.com"
        },
        "download_url": "https://api.example.com/receipts/REC-1234567890.pdf",
        "qr_code": "data:image/png;base64,..."
    }
}
```

## Subscription Endpoints

### Create Subscription
Create a new subscription.

```http
POST /api/subscriptions/create
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "amount": 99.99,
    "currency": "ZAR",
    "description": "Monthly Premium Plan",
    "customer": {
        "email": "customer@example.com",
        "name": "John Doe",
        "phone": "+27123456789"
    },
    "frequency": "monthly",
    "interval": 1,
    "total_cycles": 12,
    "start_date": "2024-02-01",
    "trial_days": 7,
    "auto_renew": true,
    "metadata": {
        "plan_id": "premium_monthly",
        "user_id": 42
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Subscription created successfully",
    "data": {
        "subscription_id": 1,
        "reference": "SUB-1234567890",
        "gateway_subscription_id": "SUB-GW-123456",
        "amount": 99.99,
        "currency": "ZAR",
        "frequency": "monthly",
        "next_billing_date": "2024-02-01T00:00:00Z",
        "status": "active",
        "trial_ends_at": "2024-01-22T00:00:00Z"
    }
}
```

### Get Subscription
Get subscription details.

```http
GET /api/subscriptions/{reference}
```

**Response:**
```json
{
    "success": true,
    "message": "Subscription retrieved",
    "data": {
        "reference": "SUB-1234567890",
        "status": "active",
        "amount": 99.99,
        "currency": "ZAR",
        "frequency": "monthly",
        "interval": 1,
        "total_cycles": 12,
        "cycles_completed": 3,
        "next_billing_date": "2024-04-01T00:00:00Z",
        "last_billing_date": "2024-03-01T00:00:00Z",
        "customer": {
            "email": "customer@example.com",
            "name": "John Doe"
        },
        "metadata": {
            "plan_id": "premium_monthly"
        },
        "created_at": "2024-01-01T00:00:00Z",
        "stats": {
            "total_paid": 299.97,
            "successful_payments": 3,
            "failed_payments": 0,
            "success_rate": 100.00
        }
    }
}
```

### Cancel Subscription
Cancel an active subscription.

```http
POST /api/subscriptions/cancel
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "reference": "SUB-1234567890",
    "reason": "Customer cancellation",
    "cancel_at_period_end": true
}
```

**Response:**
```json
{
    "success": true,
    "message": "Subscription cancelled successfully",
    "data": {
        "cancelled": true,
        "cancelled_at": "2024-01-15T10:30:00Z",
        "cancelled_by": "customer",
        "cancellation_reason": "Customer cancellation",
        "ends_at": "2024-04-01T00:00:00Z",
        "subscription": {
            "reference": "SUB-1234567890",
            "status": "cancelled"
        }
    }
}
```

### Pause Subscription
Pause an active subscription.

```http
POST /api/subscriptions/pause
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "reference": "SUB-1234567890",
    "reason": "Temporary hold"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Subscription paused successfully",
    "data": {
        "paused": true,
        "paused_at": "2024-01-15T10:30:00Z",
        "resumes_at": "2024-02-15T00:00:00Z",
        "subscription": {
            "reference": "SUB-1234567890",
            "status": "paused"
        }
    }
}
```

### Resume Subscription
Resume a paused subscription.

```http
POST /api/subscriptions/resume
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "reference": "SUB-1234567890"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Subscription resumed successfully",
    "data": {
        "resumed": true,
        "resumed_at": "2024-01-15T10:30:00Z",
        "next_billing_date": "2024-02-01T00:00:00Z",
        "subscription": {
            "reference": "SUB-1234567890",
            "status": "active"
        }
    }
}
```

### Update Subscription Payment Method
Update the payment method for a subscription.

```http
POST /api/subscriptions/update-payment-method
```

**Request Body:**
```json
{
    "gateway": "payfast",
    "reference": "SUB-1234567890",
    "payment_method": {
        "type": "credit_card",
        "card_number": "4111111111111111",
        "expiry_month": "12",
        "expiry_year": "2025",
        "cvv": "123"
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment method updated successfully",
    "data": {
        "updated": true,
        "updated_at": "2024-01-15T10:30:00Z",
        "payment_method": "credit_card",
        "subscription": {
            "reference": "SUB-1234567890"
        }
    }
}
```

### Get Subscription Transactions
Get all transactions for a subscription.

```http
GET /api/subscriptions/{reference}/transactions
```

**Query Parameters:**
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 20)
- `status` - Filter by status
- `start_date` - Start date filter
- `end_date` - End date filter

**Response:**
```json
{
    "success": true,
    "message": "Subscription transactions retrieved",
    "data": [
        {
            "id": 1,
            "reference": "PAY-1234567890",
            "amount": 99.99,
            "currency": "ZAR",
            "status": "completed",
            "created_at": "2024-01-01T00:00:00Z",
            "description": "Monthly Premium Plan - January 2024"
        }
    ],
    "meta": {
        "total": 3,
        "count": 1,
        "per_page": 20,
        "current_page": 1,
        "total_pages": 1
    }
}
```

## Transaction Endpoints

### List Transactions
List all transactions with filtering and pagination.

```http
GET /api/transactions
```

**Query Parameters:**
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 20)
- `gateway` - Filter by payment gateway
- `status` - Filter by status
- `customer_email` - Filter by customer email
- `start_date` - Start date filter (YYYY-MM-DD)
- `end_date` - End date filter (YYYY-MM-DD)
- `min_amount` - Minimum amount filter
- `max_amount` - Maximum amount filter
- `sort_by` - Sort field (created_at, amount, etc.)
- `sort_order` - Sort order (asc, desc)

**Response:**
```json
{
    "success": true,
    "message": "Transactions retrieved successfully",
    "data": [
        {
            "id": 1,
            "reference": "PAY-1234567890",
            "gateway": "payfast",
            "amount": 100.00,
            "currency": "ZAR",
            "status": "completed",
            "customer": {
                "name": "John Doe",
                "email": "customer@example.com"
            },
            "created_at": "2024-01-15T10:30:00Z",
            "description": "Product Purchase"
        }
    ],
    "meta": {
        "total": 150,
        "count": 20,
        "per_page": 20,
        "current_page": 1,
        "total_pages": 8,
        "links": {
            "first": "/api/transactions?page=1",
            "last": "/api/transactions?page=8",
            "prev": null,
            "next": "/api/transactions?page=2"
        }
    },
    "summary": {
        "total_amount": 15000.00,
        "total_transactions": 150,
        "status_counts": {
            "completed": 120,
            "pending": 15,
            "failed": 10,
            "refunded": 5
        },
        "gateway_counts": {
            "payfast": 80,
            "paystack": 40,
            "paypal": 30
        },
        "currency_counts": {
            "ZAR": 100,
            "USD": 50
        },
        "success_rate": 80.00,
        "average_transaction_value": 100.00,
        "daily_totals": [
            {
                "date": "2024-01-15",
                "count": 10,
                "amount": 1000.00
            }
        ]
    }
}
```

### Get Transaction Details
Get detailed information about a specific transaction.

```http
GET /api/transactions/{id}
```

**Response:**
```json
{
    "success": true,
    "message": "Transaction details retrieved",
    "data": {
        "id": 1,
        "reference": "PAY-1234567890",
        "gateway": "payfast",
        "gateway_name": "PayFast",
        "gateway_icon": "bolt",
        "amount": 100.00,
        "currency": "ZAR",
        "description": "Product Purchase",
        "status": "completed",
        "customer": {
            "name": "John Doe",
            "email": "customer@example.com",
            "phone": "+27123456789",
            "address": "123 Main St, Johannesburg"
        },
        "metadata": {
            "order_id": 12345,
            "product_id": 67890
        },
        "gateway_response": {
            "payment_status": "COMPLETE",
            "amount_gross": "100.00",
            "amount_fee": "3.50",
            "amount_net": "96.50"
        },
        "gateway_transaction_id": "PF-1234567890",
        "gateway_reference": "m_payment_id",
        "payment_method": "credit_card",
        "payment_method_details": {
            "card_type": "Visa",
            "last_four": "1111"
        },
        "ip_address": "192.168.1.1",
        "user_agent": "Mozilla/5.0...",
        "webhook_received": true,
        "webhook_processed": true,
        "refunded": false,
        "refund_amount": null,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:31:00Z",
        "completed_at": "2024-01-15T10:31:00Z",
        "links": {
            "self": "/api/transactions/1",
            "refund": "/api/payments/refund",
            "receipt": "/api/payments/PAY-1234567890/receipt"
        }
    },
    "meta": {
        "gateway_status": {
            "code": "completed",
            "message": "Payment completed successfully",
            "is_final": true,
            "can_retry": false,
            "can_refund": true,
            "can_cancel": false
        }
    }
}
```

### Export Transactions
Export transactions to various formats.

```http
GET /api/transactions/export
```

**Query Parameters:**
- `format` - Export format: `csv` (default), `excel`, `json`, `pdf`
- `start_date` - Start date filter
- `end_date` - End date filter
- `gateway` - Filter by gateway
- `status` - Filter by status
- `columns` - Comma-separated list of columns to include

**Response Headers:**
- `Content-Type` - Depends on format (text/csv, application/vnd.ms-excel, etc.)
- `Content-Disposition` - Attachment filename

## Gateway Endpoints

### List Gateways
Get list of all available payment gateways.

```http
GET /api/gateways
```

**Response:**
```json
{
    "success": true,
    "message": "Gateways retrieved successfully",
    "data": [
        {
            "code": "payfast",
            "name": "PayFast",
            "description": "South African payment gateway",
            "icon": "bolt",
            "color": "#28a745",
            "type": "card",
            "category": "local",
            "enabled": true,
            "test_mode": true,
            "supported_currencies": ["ZAR", "USD"],
            "supported_countries": ["ZA"],
            "minimum_amount": 1.00,
            "maximum_amount": 1000000.00,
            "stats": {
                "total_transactions": 150,
                "total_amount": 15000.00,
                "success_rate": 85.33,
                "average_transaction_value": 100.00
            },
            "status": {
                "operational": true,
                "test_mode": true,
                "configured": true,
                "connected": true,
                "health_status": "healthy"
            }
        }
    ]
}
```

### Get Gateway Details
Get detailed information about a specific gateway.

```http
GET /api/gateways/{code}
```

**Response:**
```json
{
    "success": true,
    "message": "Gateway details retrieved",
    "data": {
        "code": "payfast",
        "name": "PayFast",
        "description": "South African payment gateway supporting credit cards, debit cards, and EFT",
        "icon": "bolt",
        "color": "#28a745",
        "type": "card",
        "type_label": "Card Payments",
        "category": "local",
        "category_label": "Local",
        "enabled": true,
        "test_mode": true,
        "supported_currencies": ["ZAR", "USD"],
        "supported_countries": ["ZA"],
        "minimum_amount": 1.00,
        "maximum_amount": 1000000.00,
        "transaction_fee_type": "percentage",
        "transaction_fee_percentage": 3.5,
        "settlement_days": 3,
        "auto_refund_enabled": true,
        "auto_refund_days": 30,
        "webhook_support": true,
        "recurring_payment_support": true,
        "partial_refund_support": true,
        "instant_settlement": false,
        "requires_redirect": true,
        "requires_3ds": true,
        "config": {
            "merchant_id": "10000100",
            "merchant_key": "46f0cd694581a",
            "passphrase": "test_passphrase",
            "return_url": "https://example.com/payment/success",
            "cancel_url": "https://example.com/payment/cancel",
            "notify_url": "https://example.com/webhooks/payfast"
        },
        "credentials": {
            "merchant_id": "10000100",
            "merchant_key": "***********581a",
            "passphrase": "***************"
        },
        "stats": {
            "total_transactions": 150,
            "total_amount": 15000.00,
            "success_rate": 85.33,
            "average_transaction_value": 100.00,
            "pending_transactions": 5,
            "failed_transactions": 10,
            "refunded_transactions": 3
        },
        "status": {
            "operational": true,
            "test_mode": true,
            "configured": true,
            "connected": true,
            "last_check": "2024-01-15T10:00:00Z",
            "health_status": "healthy",
            "health_message": "Gateway is operating normally",
            "maintenance_mode": false
        },
        "capabilities": {
            "payment_methods": ["credit_card", "debit_card", "eft", "instant_eft"],
            "features": ["webhooks", "subscriptions", "partial_refunds", "auto_refunds", "3d_secure"],
            "limits": {
                "minimum_amount": 1.00,
                "maximum_amount": 1000000.00,
                "daily_limit": 50000.00,
                "monthly_limit": 1000000.00
            },
            "settlement": {
                "instant": false,
                "days": 3,
                "schedule": "daily"
            }
        }
    }
}
```

### Test Gateway Connection
Test the connection to a payment gateway.

```http
POST /api/gateways/{code}/test
```

**Response:**
```json
{
    "success": true,
    "message": "Gateway connection test completed",
    "data": {
        "connected": true,
        "response_time": 245,
        "gateway_status": "operational",
        "test_transaction": {
            "initiated": true,
            "reference": "TEST-1234567890",
            "amount": 1.00,
            "currency": "ZAR"
        },
        "details": {
            "api_version": "v1",
            "merchant_status": "active",
            "balance": 15000.00,
            "currency": "ZAR"
        }
    }
}
```

### Update Gateway Configuration
Update gateway configuration (admin only).

```http
PUT /api/gateways/{code}/config
```

**Request Body:**
```json
{
    "enabled": true,
    "test_mode": false,
    "config": {
        "merchant_id": "new_merchant_id",
        "merchant_key": "new_merchant_key",
        "passphrase": "new_passphrase"
    },
    "credentials": {
        "api_key": "new_api_key",
        "api_secret": "new_api_secret"
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Gateway configuration updated",
    "data": {
        "updated": true,
        "gateway": "payfast",
        "config_updated": true,
        "credentials_updated": true,
        "test_mode": false
    }
}
```

### Get Gateway Transactions
Get transactions for a specific gateway.

```http
GET /api/gateways/{code}/transactions
```

**Query Parameters:** Same as transaction listing

**Response:** Same format as transaction listing

## Webhook Endpoints

### Process Webhook
Process incoming webhook from payment gateway.

```http
POST /api/webhooks/{gateway}
```

**Headers:**
- `X-Gateway-Signature` - Gateway signature for verification
- `X-Gateway-Timestamp` - Webhook timestamp
- `Content-Type` - `application/json` or `application/x-www-form-urlencoded`

**Request Body:** Gateway-specific webhook data

**Response:**
```json
{
    "success": true,
    "message": "Webhook processed successfully",
    "data": {
        "processed": true,
        "transaction": {
            "reference": "PAY-1234567890",
            "status": "completed",
            "gateway_status": "COMPLETE"
        },
        "webhook_type": "payment_completed",
        "webhook_id": "WH-1234567890",
        "processed_at": "2024-01-15T10:31:00Z"
    }
}
```

### List Webhook Events
Get list of webhook events for debugging.

```http
GET /api/webhooks/events
```

**Query Parameters:**
- `gateway` - Filter by gateway
- `type` - Filter by event type
- `start_date` - Start date filter
- `end_date` - End date filter
- `page` - Page number
- `limit` - Items per page

**Response:**
```json
{
    "success": true,
    "message": "Webhook events retrieved",
    "data": [
        {
            "id": 1,
            "gateway": "payfast",
            "event_type": "payment_completed",
            "payload": {
                "payment_status": "COMPLETE",
                "amount_gross": "100.00"
            },
            "signature": "abc123...",
            "verified": true,
            "processed": true,
            "processing_time": 150,
            "created_at": "2024-01-15T10:31:00Z",
            "processed_at": "2024-01-15T10:31:15Z"
        }
    ],
    "meta": {
        "total": 50,
        "count": 20,
        "per_page": 20,
        "current_page": 1,
        "total_pages": 3
    }
}
```

### Retry Webhook Processing
Retry processing a failed webhook.

```http
POST /api/webhooks/{id}/retry
```

**Response:**
```json
{
    "success": true,
    "message": "Webhook retry initiated",
    "data": {
        "retry_initiated": true,
        "webhook_id": 1,
        "attempts": 2,
        "next_attempt": "2024-01-15T11:31:00Z"
    }
}
```

## Analytics Endpoints

### Get Payment Statistics
Get overall payment statistics.

```http
GET /api/statistics
```

**Query Parameters:**
- `period` - Time period: `today`, `yesterday`, `week`, `month`, `quarter`, `year`, `custom`
- `start_date` - Custom start date (required if period=custom)
- `end_date` - Custom end date (required if period=custom)
- `gateway` - Filter by gateway
- `currency` - Filter by currency

**Response:**
```json
{
    "success": true,
    "message": "Statistics retrieved successfully",
    "data": {
        "overview": {
            "total_revenue": 15000.00,
            "total_transactions": 150,
            "successful_transactions": 120,
            "failed_transactions": 10,
            "pending_transactions": 15,
            "refunded_transactions": 5,
            "conversion_rate": 80.00,
            "average_transaction_value": 100.00,
            "total_fees": 525.00,
            "net_revenue": 14475.00
        },
        "daily_totals": [
            {
                "date": "2024-01-15",
                "transactions": 10,
                "revenue": 1000.00,
                "successful": 8,
                "failed": 1,
                "pending": 1
            }
        ],
        "gateway_distribution": {
            "payfast": {
                "transactions": 80,
                "revenue": 8000.00,
                "percentage": 53.33
            },
            "paystack": {
                "transactions": 40,
                "revenue": 4000.00,
                "percentage": 26.67
            },
            "paypal": {
                "transactions": 30,
                "revenue": 3000.00,
                "percentage": 20.00
            }
        },
        "status_distribution": {
            "completed": 120,
            "pending": 15,
            "failed": 10,
            "refunded": 5
        },
        "revenue_trend": {
            "labels": ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
            "data": [1000, 1500, 1200, 1800, 2000, 2200],
            "growth_rate": 20.00
        },
        "conversion_rate": {
            "overall": 80.00,
            "by_gateway": {
                "payfast": 85.00,
                "paystack": 75.00,
                "paypal": 70.00
            },
            "trend": [75, 78, 80, 82, 80, 85]
        }
    }
}
```

### Get Revenue Report
Get detailed revenue report.

```http
GET /api/statistics/revenue
```

**Query Parameters:** Same as statistics endpoint

**Response:**
```json
{
    "success": true,
    "message": "Revenue report generated",
    "data": {
        "summary": {
            "gross_revenue": 15000.00,
            "net_revenue": 14475.00,
            "total_fees": 525.00,
            "total_refunds": 250.00,
            "total_chargebacks": 0.00,
            "average_daily_revenue": 500.00
        },
        "daily_breakdown": [
            {
                "date": "2024-01-15",
                "gross_revenue": 1000.00,
                "fees": 35.00,
                "refunds": 50.00,
                "net_revenue": 915.00,
                "transactions": 10
            }
        ],
        "gateway_breakdown": [
            {
                "gateway": "payfast",
                "gross_revenue": 8000.00,
                "fees": 280.00,
                "net_revenue": 7720.00,
                "percentage": 53.33
            }
        ],
        "currency_breakdown": [
            {
                "currency": "ZAR",
                "gross_revenue": 10000.00,
                "net_revenue": 9650.00,
                "percentage": 66.67
            }
        ]
    }
}
```

## Admin Endpoints

**Note:** All admin endpoints require `admin:all` permission scope.

### Admin List Transactions
Admin endpoint for transaction management.

```http
GET /api/admin/transactions
```

**Query Parameters:** Same as regular transaction listing plus:
- `include_deleted` - Include soft-de