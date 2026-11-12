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
            DB::statement("CREATE TABLE investments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                plan_id BIGINT NOT NULL,
                period DATE NOT NULL,
                unit_price BIGINT NOT NULL,
                price_date DATE NOT NULL,
                quantity_before BIGINT NOT NULL,
                target_amount BIGINT NOT NULL,
                portfolio_value_before BIGINT NOT NULL,
                theoretical_investment BIGINT NOT NULL,
                proposed_quantity BIGINT NOT NULL,
                proposed_amount BIGINT NOT NULL,
                purchased_quantity BIGINT NOT NULL,
                purchased_amount BIGINT NOT NULL,
                quantity_after BIGINT NOT NULL,
                invested_amount_after BIGINT NOT NULL,
                virtual_liquidity_increase BIGINT NOT NULL,
                virtual_liquidity_after BIGINT NOT NULL,
                range_status VARCHAR(32) NOT NULL,
                rounding_exceeds_increment BOOLEAN NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT investments_plan_fk FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
                CONSTRAINT investments_plan_period_unique UNIQUE (plan_id, period),
                CONSTRAINT investments_integer_values CHECK (typeof(unit_price) = 'integer' AND typeof(quantity_before) = 'integer' AND typeof(target_amount) = 'integer' AND typeof(portfolio_value_before) = 'integer' AND typeof(theoretical_investment) = 'integer' AND typeof(proposed_quantity) = 'integer' AND typeof(proposed_amount) = 'integer' AND typeof(purchased_quantity) = 'integer' AND typeof(purchased_amount) = 'integer' AND typeof(quantity_after) = 'integer' AND typeof(invested_amount_after) = 'integer' AND typeof(virtual_liquidity_increase) = 'integer' AND typeof(virtual_liquidity_after) = 'integer'),
                CONSTRAINT investments_period_month CHECK (length(period) = 10 AND date(period, '+0 days') IS NOT NULL AND date(period, '+0 days') = period AND substr(period, 9, 2) = '01'),
                CONSTRAINT investments_price_date_valid CHECK (length(price_date) = 10 AND date(price_date, '+0 days') IS NOT NULL AND date(price_date, '+0 days') = price_date),
                CONSTRAINT investments_price_positive CHECK (unit_price > 0),
                CONSTRAINT investments_nonnegative CHECK (quantity_before >= 0 AND target_amount >= 0 AND portfolio_value_before >= 0 AND proposed_quantity >= 0 AND proposed_amount >= 0 AND purchased_quantity >= 0 AND purchased_amount >= 0 AND quantity_after >= 0 AND invested_amount_after >= 0 AND virtual_liquidity_increase >= 0 AND virtual_liquidity_after >= 0),
                CONSTRAINT investments_range_status_valid CHECK (range_status IN ('in_range', 'above_increment', 'virtual_liquidity')),
                CONSTRAINT investments_rounding_boolean CHECK (rounding_exceeds_increment IN (0, 1))
            )");

            return;
        }

        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->bigInteger('unit_price');
            $table->date('price_date');
            $table->bigInteger('quantity_before');
            $table->bigInteger('target_amount');
            $table->bigInteger('portfolio_value_before');
            $table->bigInteger('theoretical_investment');
            $table->bigInteger('proposed_quantity');
            $table->bigInteger('proposed_amount');
            $table->bigInteger('purchased_quantity');
            $table->bigInteger('purchased_amount');
            $table->bigInteger('quantity_after');
            $table->bigInteger('invested_amount_after');
            $table->bigInteger('virtual_liquidity_increase');
            $table->bigInteger('virtual_liquidity_after');
            $table->string('range_status', 32);
            $table->boolean('rounding_exceeds_increment');
            $table->unique(['plan_id', 'period']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
