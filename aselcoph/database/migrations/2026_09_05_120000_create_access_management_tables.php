<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (! Schema::hasColumn('departments', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('departments', 'status')) {
                    $table->string('status', 20)->default('active');
                }
                if (! Schema::hasColumn('departments', 'head_user_id')) {
                    $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('departments', 'contact')) {
                    $table->string('contact')->nullable();
                }
                if (! Schema::hasColumn('departments', 'operating_schedule')) {
                    $table->json('operating_schedule')->nullable();
                }
                if (! Schema::hasColumn('departments', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('departments', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        } else {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('contact')->nullable();
                $table->json('operating_schedule')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('user_type', 20)->default('support');
            $table->string('scope', 20)->default('department');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('module', 40);
            $table->string('action', 20);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('department_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['department_id', 'permission_id']);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('allowed')->default(true);
            $table->unique(['user_id', 'permission_id']);
        });

        Schema::create('department_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'employee_ref')) {
                $table->string('employee_ref', 60)->nullable();
            }
            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable();
            }
            if (! Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 20)->default('customer')->index();
            }
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status', 30)->default('active')->index();
            }
            if (! Schema::hasColumn('users', 'availability_status')) {
                $table->string('availability_status', 20)->default('offline');
            }
            if (! Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'supervisor_id')) {
                $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'failed_login_count')) {
                $table->unsignedTinyInteger('failed_login_count')->default(0);
            }
            if (! Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable();
            }
            if (! Schema::hasColumn('users', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('team')->nullable();
            $table->text('responsibilities')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('user_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('offline');
            $table->string('shift', 20)->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('on_leave')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('user_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('shift', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('user_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('skill', 40);
            $table->timestamps();
            $table->unique(['user_id', 'skill']);
        });

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('ticket_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('previous_department', 40)->nullable();
            $table->string('new_department', 40)->nullable();
            $table->foreignId('previous_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 40);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('ai_recommendation')->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('access_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_settings');
        Schema::dropIfExists('ticket_assignment_history');
        Schema::dropIfExists('user_activity_logs');
        Schema::dropIfExists('user_skills');
        Schema::dropIfExists('user_schedules');
        Schema::dropIfExists('user_availability');
        Schema::dropIfExists('user_profiles');

        Schema::table('users', function (Blueprint $table) {
            foreach (['created_by', 'updated_by', 'supervisor_id', 'department_id', 'role_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach ([
                'username', 'employee_ref', 'position', 'user_type', 'account_status',
                'availability_status', 'last_login_at', 'failed_login_count', 'locked_until',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('department_users');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('department_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
