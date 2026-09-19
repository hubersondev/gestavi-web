<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 30);
            $table->string('status', 20)->default('pending');

            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count')->default(1);

            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['employee_id', 'start_date']);
        });

        DB::statement("ALTER TABLE leaves ADD CONSTRAINT leaves_type_check CHECK (type IN ('annual','sick','maternity','paternity','unpaid','exceptional'))");
        DB::statement("ALTER TABLE leaves ADD CONSTRAINT leaves_status_check CHECK (status IN ('pending','approved','rejected','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
