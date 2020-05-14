<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPanelLabelToPanelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('panels', function (Blueprint $table) {
            if (!Schema::hasColumn('panels', 'panel_label')) {
                $table->string('panel_label')->nullable()->after('panel');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('panels', function (Blueprint $table) {
            if (Schema::hasColumn('panels', 'panel_label')) {
                $table->dropColumn('panel_label');
            }
        });
    }
}
