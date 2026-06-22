<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique()->comment('用户名');
            $table->string('email', 100)->unique()->nullable()->comment('邮箱');
            $table->string('phone', 20)->unique()->nullable()->comment('手机号');
            $table->string('password', 255)->comment('密码');
            $table->string('nickname', 50)->nullable()->comment('昵称');
            $table->string('avatar', 255)->nullable()->comment('头像');
            $table->unsignedBigInteger('dealer_level_id')->nullable()->comment('经销商等级ID');
            $table->decimal('total_achievement', 15, 2)->default(0)->comment('累计业绩');
            $table->decimal('current_month_achievement', 15, 2)->default(0)->comment('当月业绩');
            $table->integer('total_invite_count')->default(0)->comment('累计邀请人数');
            $table->unsignedBigInteger('inviter_id')->nullable()->comment('邀请人用户ID');
            $table->string('invite_path', 500)->nullable()->comment('邀请链路路径，格式：ID1-ID2-ID3');
            $table->integer('invite_depth')->default(0)->comment('邀请深度');
            $table->string('api_token', 80)->unique()->nullable()->comment('API Token');
            $table->tinyInteger('status')->default(1)->comment('状态：1正常 0禁用');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->comment('最后登录IP');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->index('dealer_level_id');
            $table->index('inviter_id');
            $table->index('status');
            $table->foreign('dealer_level_id')->references('id')->on('dealer_levels')->nullOnDelete();
            $table->foreign('inviter_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}
