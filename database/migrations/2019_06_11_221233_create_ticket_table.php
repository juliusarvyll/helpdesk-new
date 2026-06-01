<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {

            $table->bigIncrements('id');
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->string('priority')->default('normal'); // [low,normal,critial]
            $table->string('category')->nullable();
            $table->string('issue')->nullable();

            $table->string('client')->nullable();
            $table->text('technical_support')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('role')->nullable();

            $table->string('issue_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->string('technical_support_id')->nullable();

            $table->string('support_assignment_status')->default('Not Yet Assigned');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('status')->default('active'); // active ,  on progress ,  pending / closed ,  overdue / closed  | closed
            $table->integer('rate')->default(5);
            $table->text('technical_support_remarks')->nullable();
            $table->text('client_comments')->nullable();
            $table->string('client_confirmation')->default(0);

            $table->string('created_ticket');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tickets');
    }
}
