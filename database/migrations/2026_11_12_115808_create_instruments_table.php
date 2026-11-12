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
            DB::statement("CREATE TABLE instruments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticker VARCHAR(32) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                latest_price BIGINT NULL,
                latest_price_date DATE NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT instruments_ticker_normalized CHECK (length(ticker) BETWEEN 1 AND 32 AND ticker = upper(trim(ticker))),
                CONSTRAINT instruments_name_length CHECK (length(trim(name)) BETWEEN 1 AND 255),
                CONSTRAINT instruments_latest_price_positive CHECK (latest_price IS NULL OR (typeof(latest_price) = 'integer' AND latest_price > 0)),
                CONSTRAINT instruments_latest_price_pair CHECK ((latest_price IS NULL AND latest_price_date IS NULL) OR (latest_price IS NOT NULL AND latest_price_date IS NOT NULL AND length(latest_price_date) = 10 AND date(latest_price_date, '+0 days') IS NOT NULL AND date(latest_price_date, '+0 days') = latest_price_date))
            )");

            return;
        }

        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 32)->unique();
            $table->string('name');
            $table->bigInteger('latest_price')->nullable();
            $table->date('latest_price_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
