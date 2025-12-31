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
        Schema::create("payment_subscriptions", function (Blueprint $table) {
            $table->id();

            // Subscription identification
            $table->string("reference")->unique();
            $table->string("gateway");
            $table->string("gateway_subscription_id")->nullable()->index();
            $table->string("gateway_customer_id")->nullable()->index();

            // User relationship (polymorphic)
            $table->unsignedBigInteger("user_id")->nullable();
            $table->string("user_type")->nullable();

            // Subscription details
            $table->decimal("amount", 15, 2);
            $table->string("currency", 3)->default("ZAR");
            $table->string("status")->default("active")->index();
            $table->text("description")->nullable();

            // Customer information
            $table->string("customer_email")->nullable()->index();
            $table->string("customer_name")->nullable();
            $table->string("customer_phone")->nullable();

            // Payment method details
            $table->string("payment_method")->nullable();
            $table->string("card_last_four", 4)->nullable();
            $table->string("card_brand")->nullable();
            $table->string("card_expiry_month", 2)->nullable();
            $table->string("card_expiry_year", 4)->nullable();

            // Subscription frequency and billing
            $table->string("frequency")->default("monthly")->index(); // daily, weekly, monthly, quarterly, yearly
            $table->string("interval")->nullable(); // stripe-style interval
            $table->integer("interval_count")->default(1);
            $table->timestamp("start_date")->nullable();
            $table->timestamp("end_date")->nullable();
            $table->timestamp("current_period_start")->nullable();
            $table->timestamp("current_period_end")->nullable();
            $table->boolean("cancel_at_period_end")->default(false);
            $table->timestamp("cancelled_at")->nullable();
            $table->text("cancel_reason")->nullable();

            // Trial period
            $table->timestamp("trial_start")->nullable();
            $table->timestamp("trial_end")->nullable();

            // Billing configuration
            $table->timestamp("billing_cycle_anchor")->nullable();
            $table->integer("days_until_due")->nullable();
            $table
                ->string("collection_method")
                ->default("charge_automatically"); // charge_automatically, send_invoice

            // Metadata and notes
            $table->json("metadata")->nullable();
            $table->text("notes")->nullable();

            // Retry mechanism
            $table->integer("attempts")->default(0);
            $table->integer("max_attempts")->default(3);
            $table->timestamp("retry_at")->nullable();

            // Payment history
            $table->timestamp("last_payment_date")->nullable();
            $table->unsignedBigInteger("last_transaction_id")->nullable();
            $table->integer("total_payments")->default(0);
            $table->decimal("total_amount", 15, 2)->default(0);

            // Next billing
            $table->timestamp("next_billing_date")->nullable()->index();

            // Grace period for failed payments
            $table->timestamp("grace_period_ends_at")->nullable();

            // Auto-renewal setting
            $table->boolean("auto_renew")->default(true);

            // Indexes
            $table->index(["user_id", "user_type"]);
            $table->index(["status", "next_billing_date"]);
            $table->index(["gateway", "status"]);
            $table->index(["customer_email", "status"]);
            $table->index(["frequency", "status"]);
            $table->index(["auto_renew", "next_billing_date"]);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table
                ->foreign("last_transaction_id")
                ->references("id")
                ->on("payment_transactions")
                ->onDelete("set null");
        });

        // Add comment to table (commented out for SQLite compatibility)
        // DB::statement("COMMENT ON TABLE payment_subscriptions IS 'Stores subscription/recurring payment information for the payment gateway system'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("payment_subscriptions");
    }
};
