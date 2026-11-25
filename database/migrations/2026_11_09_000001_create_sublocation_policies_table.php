<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sublocation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('section')->unique();
            $table->boolean('reserved')->default(false);
            $table->unsignedSmallInteger('student_loan_duration_days')->nullable();
            $table->unsignedSmallInteger('employee_loan_duration_days')->nullable();
            $table->decimal('student_fine_per_day', 8, 2)->nullable();
            $table->decimal('employee_fine_per_day', 8, 2)->nullable();
            $table->decimal('student_max_fine', 10, 2)->nullable();
            $table->decimal('employee_max_fine', 10, 2)->nullable();
            $table->unsignedSmallInteger('student_grace_period_days')->nullable();
            $table->unsignedSmallInteger('employee_grace_period_days')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['section' => 'Circulation', 'reserved' => false, 'sort_order' => 10],
            ['section' => 'Fiction', 'reserved' => false, 'sort_order' => 20],
            ['section' => 'Filipiniana', 'reserved' => false, 'sort_order' => 30],
            ['section' => 'Periodicals', 'reserved' => false, 'sort_order' => 40],
            ['section' => 'Reference', 'reserved' => true, 'sort_order' => 50],
            ['section' => 'Reserve', 'reserved' => true, 'sort_order' => 60],
        ];

        foreach ($defaults as $row) {
            DB::table('sublocation_policies')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $options = array_column($defaults, 'section');

        $updated = DB::table('marc_fields')
            ->where('tag', '852')
            ->where('subfield', 'c')
            ->update([
                'input_type' => 'select',
                'options' => json_encode($options),
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            DB::table('marc_fields')->insert([
                'tag' => '852',
                'subfield' => 'c',
                'label' => 'Sublocation / shelving (local)',
                'repeatable' => false,
                'input_type' => 'select',
                'options' => json_encode($options),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sublocation_policies');
    }
};
