<?php

namespace Lisosoft\PaymentGateway;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use Lisosoft\PaymentGateway\Console\Commands\InstallPaymentGateway;
use Lisosoft\PaymentGateway\Console\Commands\TestPaymentGateway;
use Lisosoft\PaymentGateway\Console\Commands\ListTransactions;
use Lisosoft\PaymentGateway\Console\Commands\ProcessRecurringPayments;
use Lisosoft\PaymentGateway\Services\PaymentManager;
use Lisosoft\PaymentGateway\Services\PaymentAnalytics;
use Lisosoft\PaymentGateway\Services\RecurringPaymentService;
use Lisosoft\PaymentGateway\Services\PaymentReportService;
use Lisosoft\PaymentGateway\Events\PaymentCompleted;
use Lisosoft\PaymentGateway\Events\PaymentFailed;
use Lisosoft\PaymentGateway\Listeners\HandlePaymentCompleted;
use Lisosoft\PaymentGateway\Listeners\HandlePaymentFailed;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . "/../config/payment-gateway.php",
            "payment-gateway",
        );

        // Register the payment manager as a singleton
        $this->app->singleton("payment.manager", function ($app) {
            return new PaymentManager($app);
        });

        // Register payment analytics service
        $this->app->singleton("payment.analytics", function ($app) {
            return new PaymentAnalytics(
                $app["config"]->get("payment-gateway.analytics"),
            );
        });

        // Register recurring payment service
        $this->app->singleton("payment.recurring", function ($app) {
            return new RecurringPaymentService(
                $app["config"]->get("payment-gateway.recurring"),
            );
        });

        // Register payment report service
        $this->app->singleton("payment.reports", function ($app) {
            return new PaymentReportService(
                $app["config"]->get("payment-gateway.analytics"),
            );
        });

        // Register facade
        $this->app->bind("payment", function ($app) {
            return $app->make("payment.manager");
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes(
            [
                __DIR__ . "/../config/payment-gateway.php" => config_path(
                    "payment-gateway.php",
                ),
            ],
            "payment-gateway-config",
        );

        // Publish migrations
        $this->publishes(
            [
                __DIR__ . "/../database/migrations/" => database_path(
                    "migrations",
                ),
            ],
            "payment-gateway-migrations",
        );

        // Publish views
        $this->publishes(
            [
                __DIR__ . "/../resources/views" => resource_path(
                    "views/vendor/payment-gateway",
                ),
            ],
            "payment-gateway-views",
        );

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . "/../database/migrations");

        // Load views
        $this->loadViewsFrom(
            __DIR__ . "/../resources/views",
            "payment-gateway",
        );

        // Load routes
        $this->loadRoutesFrom(__DIR__ . "/../routes/web.php");
        $this->loadRoutesFrom(__DIR__ . "/../routes/api.php");

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallPaymentGateway::class,
                TestPaymentGateway::class,
                ListTransactions::class,
                ProcessRecurringPayments::class,
            ]);
        }

        // Register event listeners
        Event::listen(PaymentCompleted::class, HandlePaymentCompleted::class);
        Event::listen(PaymentFailed::class, HandlePaymentFailed::class);

        // Register middleware aliases
        $this->app["router"]->aliasMiddleware(
            "payment.rate-limit",
            \Lisosoft\PaymentGateway\Http\Middleware\RateLimitPayments::class,
        );
        $this->app["router"]->aliasMiddleware(
            "payment.validate-amount",
            \Lisosoft\PaymentGateway\Http\Middleware\ValidatePaymentAmount::class,
        );
        $this->app["router"]->aliasMiddleware(
            "payment.verify-webhook",
            \Lisosoft\PaymentGateway\Http\Middleware\VerifyWebhookSignature::class,
        );

        // Register validation rule
        $this->app["validator"]->extend(
            "valid_payment_gateway",
            function ($attribute, $value, $parameters, $validator) {
                $rule = new \Lisosoft\PaymentGateway\Rules\ValidPaymentGateway();
                return $rule->passes($attribute, $value);
            },
            "The selected payment gateway is invalid.",
        );

        // Register macros
        $this->registerMacros();
    }

    /**
     * Register custom macros.
     *
     * @return void
     */
    protected function registerMacros()
    {
        // Register a macro for the Request class to easily get payment data
        \Illuminate\Http\Request::macro("paymentData", function () {
            return [
                "amount" => $this->input("amount"),
                "currency" => $this->input(
                    "currency",
                    config("payment-gateway.transaction.currency"),
                ),
                "description" => $this->input(
                    "description",
                    config("payment-gateway.transaction.default_description"),
                ),
                "reference" => $this->input("reference", uniqid("PAY-")),
                "customer" => [
                    "email" => $this->input("customer_email"),
                    "name" => $this->input("customer_name"),
                    "phone" => $this->input("customer_phone"),
                ],
                "metadata" => $this->input("metadata", []),
            ];
        });

        // Register a macro for the Response class to handle payment responses
        \Illuminate\Http\Response::macro("paymentResponse", function (
            $success,
            $message,
            $data = [],
            $status = 200,
        ) {
            return response()->json(
                [
                    "success" => $success,
                    "message" => $message,
                    "data" => $data,
                    "timestamp" => now()->toISOString(),
                ],
                $status,
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            "payment.manager",
            "payment.analytics",
            "payment.recurring",
            "payment.reports",
            "payment",
        ];
    }
}
