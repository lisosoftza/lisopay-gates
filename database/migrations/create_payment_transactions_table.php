<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("payment_transactions", function (Blueprint $table) {
            $table->id();

            // Transaction identification
            $table->string("reference")->unique();
            $table->string("gateway");
            $table->string("gateway_transaction_id")->nullable()->index();

            // User relationship (polymorphic)
            $table->unsignedBigInteger("user_id")->nullable();
            $table->string("user_type")->nullable();

            // Payment details
            $table->decimal("amount", 15, 2);
            $table->string("currency", 3)->default("ZAR");
            $table->string("status")->default("pending")->index();
            $table->text("description")->nullable();

            // Customer information
            $table->string("customer_email")->nullable()->index();
            $table->string("customer_name")->nullable();
            $table->string("customer_phone")->nullable();
            $table->text("customer_address")->nullable();
            $table->text("billing_address")->nullable();
            $table->text("shipping_address")->nullable();

            // Payment method details
            $table->string("payment_method")->nullable();
            $table->string("card_last_four", 4)->nullable();
            $table->string("card_brand")->nullable();
            $table->string("card_expiry_month", 2)->nullable();
            $table->string("card_expiry_year", 4)->nullable();

            // Subscription information
            $table->boolean("is_subscription")->default(false);
            $table->string("subscription_id")->nullable()->index();
            $table->string("recurring_frequency")->nullable();
            $table->integer("recurring_cycles")->nullable();
            $table->timestamp("next_billing_date")->nullable();

            // Transaction relationships
            $table->unsignedBigInteger("parent_transaction_id")->nullable();
            $table->string("transaction_type")->default("payment")->index(); // payment, refund, chargeback

            // URLs for callbacks
            $table->string("return_url")->nullable();
            $table->string("cancel_url")->nullable();
            $table->string("webhook_url")->nullable();

            // Timestamps for status changes
            $table->timestamp("processed_at")->nullable();
            $table->timestamp("completed_at")->nullable();
            $table->timestamp("failed_at")->nullable();
            $table->timestamp("cancelled_at")->nullable();
            $table->timestamp("refunded_at")->nullable();

            // Refund information
            $table->decimal("refund_amount", 15, 2)->default(0);
            $table->text("refund_reason")->nullable();

            // Financial breakdown
            $table->decimal("fee_amount", 15, 2)->default(0);
            $table->decimal("tax_amount", 15, 2)->default(0);
            $table->decimal("discount_amount", 15, 2)->default(0);
            $table->decimal("net_amount", 15, 2)->nullable();

            // Error information
            $table->string("error_code")->nullable();
            $table->text("error_message")->nullable();
            $table->json("error_details")->nullable();

            // Retry mechanism
            $table->integer("attempts")->default(1);
            $table->integer("max_attempts")->default(3);
            $table->timestamp("retry_at")->nullable();

            // Locking mechanism for concurrent processing
            $table->timestamp("locked_until")->nullable();

            // Technical information
            $table->string("ip_address", 45)->nullable();
            $table->text("user_agent")->nullable();

            // Metadata and notes
            $table->json("metadata")->nullable();
            $table->text("notes")->nullable();

            // Indexes
            $table->index(["user_id", "user_type"]);
            $table->index(["status", "created_at"]);
            $table->index(["gateway", "status"]);
            $table->index(["customer_email", "created_at"]);
            $table->index(["is_subscription", "next_billing_date"]);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table
                ->foreign("parent_transaction_id")
                ->references("id")
                ->on("payment_transactions")
                ->onDelete("set null");
        });

        // Add comment to table (commented out for SQLite compatibility)
        // DB::statement("COMMENT ON TABLE payment_transactions IS 'Stores all payment transactions for the payment gateway system'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("payment_transactions");
    }
};
