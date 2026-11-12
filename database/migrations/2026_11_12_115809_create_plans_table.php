<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TABLE plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id BIGINT NOT NULL,
                instrument_id BIGINT NOT NULL,
                monthly_increment BIGINT NOT NULL,
                start_period DATE NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                previous_plan_id BIGINT NULL UNIQUE,
                opening_period DATE NULL,
                opening_quantity BIGINT NOT NULL DEFAULT 0,
                opening_invested_amount BIGINT NOT NULL DEFAULT 0,
                opening_target_amount BIGINT NOT NULL DEFAULT 0,
                opening_virtual_liquidity BIGINT NOT NULL DEFAULT 0,
                closed_period DATE NULL,
                configuration_locked_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT plans_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT plans_instrument_fk FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE RESTRICT,
                CONSTRAINT plans_previous_plan_fk FOREIGN KEY (previous_plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
                CONSTRAINT plans_integer_amounts CHECK (typeof(monthly_increment) = 'integer' AND typeof(opening_quantity) = 'integer' AND typeof(opening_invested_amount) = 'integer' AND typeof(opening_target_amount) = 'integer' AND typeof(opening_virtual_liquidity) = 'integer'),
                CONSTRAINT plans_increment_positive CHECK (monthly_increment > 0),
                CONSTRAINT plans_period_normalized CHECK (length(start_period) = 10 AND date(start_period, '+0 days') IS NOT NULL AND date(start_period, '+0 days') = start_period AND substr(start_period, 9, 2) = '01' AND (opening_period IS NULL OR (length(opening_period) = 10 AND date(opening_period, '+0 days') IS NOT NULL AND date(opening_period, '+0 days') = opening_period AND substr(opening_period, 9, 2) = '01')) AND (closed_period IS NULL OR (length(closed_period) = 10 AND date(closed_period, '+0 days') IS NOT NULL AND date(closed_period, '+0 days') = closed_period AND substr(closed_period, 9, 2) = '01'))),
                CONSTRAINT plans_opening_nonnegative CHECK (opening_quantity >= 0 AND opening_invested_amount >= 0 AND opening_target_amount >= 0 AND opening_virtual_liquidity >= 0),
                CONSTRAINT plans_status_valid CHECK (status IN ('active', 'closed')),
                CONSTRAINT plans_closed_consistency CHECK ((status = 'active' AND closed_period IS NULL) OR (status = 'closed' AND closed_period IS NOT NULL))
            )");
            DB::statement('CREATE INDEX plans_user_status_index ON plans(user_id, status)');
            DB::statement('CREATE INDEX plans_instrument_index ON plans(instrument_id)');

            return;
        }

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_id')->constrained()->restrictOnDelete();
            $table->bigInteger('monthly_increment');
            $table->date('start_period');
            $table->string('status', 16)->default('active');
            $table->foreignId('previous_plan_id')->nullable()->unique()->constrained('plans')->restrictOnDelete();
            $table->date('opening_period')->nullable();
            $table->bigInteger('opening_quantity')->default(0);
            $table->bigInteger('opening_invested_amount')->default(0);
            $table->bigInteger('opening_target_amount')->default(0);
            $table->bigInteger('opening_virtual_liquidity')->default(0);
            $table->date('closed_period')->nullable();
            $table->dateTime('configuration_locked_at')->nullable();
            $table->index(['user_id', 'status']);
            $table->index('instrument_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
