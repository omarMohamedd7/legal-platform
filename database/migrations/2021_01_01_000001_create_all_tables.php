<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['client', 'lawyer', 'judge'])->default('client');
            $table->text('profile_image_url')->nullable();
            $table->string('otp', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('fcm_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Create clients table
        Schema::create('clients', function (Blueprint $table) {
            $table->id('client_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('city')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        // 3. Create lawyers table
        Schema::create('lawyers', function (Blueprint $table) {
            $table->id('lawyer_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('specialization', ['Family Law', 'Civil Law', 'Criminal Law', 'Commercial Law', 'International Law']);
            $table->string('city');
            $table->string('phone_number')->nullable();
            $table->string('bio')->nullable();
            $table->decimal('consult_fee', 10, 2)->default(0);
            $table->timestamps();
        });

        // 4. Create judges table
        Schema::create('judges', function (Blueprint $table) {
            $table->id('judge_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('court_name')->nullable();
            $table->string('specialization')->nullable();
            $table->timestamps();
        });

        // 5. Create cases table
        Schema::create('cases', function (Blueprint $table) {
            $table->id('case_id');
            $table->string('case_number')->unique();
            $table->string('plaintiff_name')->nullable();
            $table->string('defendant_name')->nullable();
            $table->enum('case_type', ['Family Law', 'Civil Law', 'Criminal Law', 'Commercial Law', 'International Law']);
            $table->text('description')->nullable();
            $table->enum('status', ['Pending', 'Active', 'Closed'])->default('Pending');
            $table->json('attachments')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_lawyer_id')->nullable()->constrained('lawyers', 'lawyer_id')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Create published_cases table
        Schema::create('published_cases', function (Blueprint $table) {
            $table->id('published_case_id');
            $table->foreignId('case_id')->constrained('cases', 'case_id')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients', 'client_id')->onDelete('cascade');
            $table->enum('status', ['Active', 'Closed'])->default('Active');
            $table->string('target_city');
            $table->enum('target_specialization', ['Family Law', 'Civil Law', 'Criminal Law', 'Commercial Law', 'International Law']);
            $table->timestamps();
        });

        // 7. Create case_offers table
        Schema::create('case_offers', function (Blueprint $table) {
            $table->id('offer_id');
            $table->foreignId('published_case_id')->constrained('published_cases', 'published_case_id')->onDelete('cascade');
            $table->foreignId('lawyer_id')->constrained('lawyers', 'lawyer_id')->onDelete('cascade');
            $table->text('message')->nullable();
            $table->decimal('expected_price', 10, 2);
            $table->enum('status', ['Pending', 'Accepted', 'Rejected'])->default('Pending');
            $table->timestamps();
        });

        // 8. Create case_requests table
        Schema::create('case_requests', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('case_id')->constrained('cases', 'case_id')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients', 'client_id')->onDelete('cascade');
            $table->foreignId('lawyer_id')->constrained('lawyers', 'lawyer_id')->onDelete('cascade');
            $table->enum('status', ['Pending', 'Accepted', 'Rejected'])->default('Pending');
            $table->timestamps();
        });

        // 9. Create court_sessions table

        // 10. Create legal_books table
        Schema::create('legal_books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        // 11. Create video_analyses table
        Schema::create('video_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_id')->constrained('judges', 'judge_id')->onDelete('cascade');
            $table->string('file_path');
            $table->enum('status', ['Pending', 'Processing', 'Completed'])->default('Pending');
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 12. Create consultation_requests table
        Schema::create('consultation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients', 'client_id')->onDelete('cascade');
            $table->foreignId('lawyer_id')->constrained('lawyers', 'lawyer_id')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // 13. Create payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('consultation_request_id')->unique()->constrained('consultation_requests')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['credit_card', 'paypal', 'visa', 'mastercard', 'bank_transfer']);
            $table->enum('status', ['pending', 'successful', 'failed'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->timestamps();
        });

        // 14. Create judge_tasks table
        Schema::create('judge_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_id')->constrained('judges', 'judge_id')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('time');
            $table->string('task_type');
            $table->boolean('reminder_enabled')->default(false);
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->timestamps();
        });

        // 15. Laravel system tables
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('judge_tasks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('consultation_requests');
        Schema::dropIfExists('video_analyses');
        Schema::dropIfExists('legal_books');
        Schema::dropIfExists('court_sessions');
        Schema::dropIfExists('case_requests');
        Schema::dropIfExists('case_offers');
        Schema::dropIfExists('published_cases');
        Schema::dropIfExists('cases');
        Schema::dropIfExists('judges');
        Schema::dropIfExists('lawyers');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('users');
    }
};
