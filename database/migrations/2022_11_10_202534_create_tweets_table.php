<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tweets', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('text', 140)->index();
            $table->json('visible_for')->nullable();
            $table->json('edit_controls')->nullable();
            $table->json('edit_history_tweet_ids')->nullable();
            $table->json('replying_to_ids')->nullable();
            $table->string('reply_settings')->default('everyone');
            $table->string('lang', 8)->index();
            $table->boolean('possibly_sensitive')->default(false);
            $table->string('source')->default('Twitter Web App');
            $table->json('withheld')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('tweets', function (Blueprint $table) {
            $table->foreignId('conversation_id')->after('id')->nullable()->constrained('tweets');
            $table->foreignId('in_reply_to_tweet_id')
                ->after('visible_for')
                ->nullable()
                ->constrained('tweets');
            $table->foreignId('retweet_from_tweet_id')
                ->after('in_reply_to_tweet_id')
                ->nullable()
                ->constrained('tweets');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tweets');
    }
};
