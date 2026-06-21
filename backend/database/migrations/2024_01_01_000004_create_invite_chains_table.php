<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInviteChainsTable extends Migration
{
    public function up()
    {
        Schema::create('invite_chains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inviter_id')->comment('邀请人ID');
            $table->unsignedBigInteger('invitee_id')->comment('被邀请人ID');
            $table->unsignedBigInteger('invite_code_id')->nullable()->comment('使用的邀请码ID');
            $table->integer('depth')->default(1)->comment('相对深度，相对于直接邀请人是1');
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('佣金比例%');
            $table->decimal('total_commission', 15, 2)->default(0)->comment('累计佣金');
            $table->decimal('reward_amount', 15, 2)->default(0)->comment('邀请奖励');
            $table->boolean('is_rewarded')->default(false)->comment('是否已发放奖励');
            $table->timestamp('rewarded_at')->nullable()->comment('奖励发放时间');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['inviter_id', 'invitee_id']);
            $table->index('inviter_id');
            $table->index('invitee_id');
            $table->index('invite_code_id');
            $table->index('depth');
            $table->foreign('inviter_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invitee_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invite_code_id')->references('id')->on('invite_codes')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invite_chains');
    }
}
