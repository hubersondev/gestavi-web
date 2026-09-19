<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('flock_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('expense_number', 50);
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('category', 30);
            $table->string('status', 20)->default('unpaid');

            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            $table->string('description');
            $table->string('reference')->nullable();
            $table->string('attachment')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'expense_number']);
            $table->index(['organisation_id', 'expense_date']);
            $table->index(['organisation_id', 'category']);
            $table->index(['status', 'due_date']);
            $table->index(['farm_id', 'status']);
            $table->index(['flock_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_status_check CHECK (status IN ('unpaid','partial','paid','cancelled'))");
            DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_category_check CHECK (category IN ('feed','medicine','veterinary','fuel','utilities','rent','maintenance','equipment','transport','services','taxes','salaries','other'))");
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_positive_check CHECK (amount > 0 AND paid_amount >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
