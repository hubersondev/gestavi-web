<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Livraisons (action 34) : bon de livraison rattaché à une facture,
 * numéroté BL-AAAA-00001 par organisation, avec suivi de statut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('delivery_number', 50);
            $table->date('delivery_date');
            $table->string('status', 20)->default('planned');
            $table->string('driver_name')->nullable();
            $table->string('vehicle', 100)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 50)->nullable();
            $table->string('delivery_address')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('signature_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'delivery_number']);
            $table->index(['organisation_id', 'delivery_date']);
            $table->index(['sale_id', 'status']);
        });

        DB::statement('ALTER TABLE deliveries ADD CONSTRAINT deliveries_organisation_farm_fk FOREIGN KEY (organisation_id, farm_id) REFERENCES farms (organisation_id, id)');

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_status_check CHECK (status IN ('planned', 'in_transit', 'delivered', 'failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
