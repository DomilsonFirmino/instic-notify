<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\Year;
use App\Models\Course;
use App\Models\Category;
use App\Models\Informativo;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
            // Permissões detalhadas conforme documento
            $permissions = [
                // Admin
                'user.create',
                'user.edit',
                'user.delete',
                'user.assign_role',
                'permission.manage',
                // User self-management
                'users.view-self',
                'users.update-self',
                'informativo.create',
                'informativo.edit_any',
                'informativo.publish',
                'informativo.unpublish',
                'informativo.delete',
                'informativo.approve',
                'informativo.reject',
                'informativo.restore_version',
                'informativo.manage_attachments',
                'estrutura.categoria.manage',
                'estrutura.departamento.manage',
                'estrutura.curso.manage',
                'estrutura.ano_academico.manage',
                'auditoria.logs.view',
                'auditoria.historico.view',
                'auditoria.ciclo_vida.view',
                // Editor
                'informativo.create_own',
                'informativo.edit_own',
                'informativo.submit_for_review',
                'informativo.attach_documents',
                'informativo.set_dates',
                'informativo.view_own_history',
                // Revisor
                'informativo.list_pending',
                'informativo.review',
                'informativo.approve_review',
                'informativo.reject_review',
                'informativo.view_logs',
                'informativo.view_versions',
                // Leitor
                'informativo.view_published',
                'informativo.filter',
                'informativo.download_attachments',
                'informativo.receive_notifications',
                'informativo.subscribe_categories',
            ];

            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm]);
            }

            // Papéis
            $adminRole = Role::firstOrCreate(['name' => 'admin']);
            $editorRole = Role::firstOrCreate(['name' => 'editor']);
            $reviewerRole = Role::firstOrCreate(['name' => 'revisor']);
            $viewerRole = Role::firstOrCreate(['name' => 'leitor']);

            // Permissões por papel
            $adminPermissions = [
                'user.create', 'user.edit', 'user.delete', 'user.assign_role', 'permission.manage',
                'users.view-self', 'users.update-self',
                'informativo.create', 'informativo.edit_any', 'informativo.publish', 'informativo.unpublish',
                'informativo.delete', 'informativo.approve', 'informativo.reject', 'informativo.restore_version',
                'informativo.manage_attachments',
                'estrutura.categoria.manage', 'estrutura.departamento.manage', 'estrutura.curso.manage', 'estrutura.ano_academico.manage',
                'auditoria.logs.view', 'auditoria.historico.view', 'auditoria.ciclo_vida.view',
            ];
            $editorPermissions = [
                'informativo.create_own', 'informativo.edit_own', 'informativo.submit_for_review',
                'informativo.attach_documents', 'informativo.set_dates', 'informativo.view_own_history',
                'users.view-self', 'users.update-self',
            ];
            $reviewerPermissions = [
                'informativo.list_pending', 'informativo.review', 'informativo.approve_review',
                'informativo.reject_review', 'informativo.view_logs', 'informativo.view_versions',
            ];
            $viewerPermissions = [
                'informativo.view_published', 'informativo.filter', 'informativo.download_attachments',
                'informativo.receive_notifications', 'informativo.subscribe_categories',
                'users.view-self', 'users.update-self',
            ];

            $adminRole->syncPermissions($adminPermissions);
            $editorRole->syncPermissions($editorPermissions);
            $reviewerRole->syncPermissions($reviewerPermissions);
            $viewerRole->syncPermissions($viewerPermissions);

            // Estrutura básica
            // Departamentos fixos e únicos
            $departments = collect([
                ['name' => 'Engenharia Informatica'],
                ['name' => 'Telecomunicações'],
                ['name' => 'Informatica de Gestão'],
            ])->map(function ($data) {
                return Department::updateOrCreate($data, $data);
            });

            // Cursos fixos e únicos, vinculados ao departamento de mesmo nome
            $courses = collect([
                'Engenharia Informatica',
                'Telecomunicações',
                'Informatica de Gestão',
            ])->map(function ($name) use ($departments) {
                $department = $departments->first(function ($dep) use ($name) {
                    return $dep->name === $name;
                });
                return Course::updateOrCreate(
                    ['name' => $name],
                    ['name' => $name, 'department_id' => $department ? $department->id : null]
                );
            });

            // Anos de 1 a 5, únicos
            $years = collect(range(1, 5))->map(function ($num) {
                return Year::updateOrCreate(['name' => (string)$num], ['name' => (string)$num]);
            });

            $categories = Category::factory()->count(5)->create();

            // Usuários de exemplo (precisam de course_id e year_id)
            $baseUser = [ 'password' => bcrypt('password123') ];
            $userAdmin = User::firstOrCreate([
                'email' => 'admin@gmail.com',
            ], array_merge($baseUser, [
                'name' => 'Admin',
                'role' => 'admin',
                'course_id' => $courses->random()->id,
                'year_id' => $years->random()->id,
                'department_id' => $departments->random()->id,
            ]));
            $userEditor = User::firstOrCreate([
                'email' => 'editor@gmail.com',
            ], array_merge($baseUser, [
                'name' => 'Editor',
                'role' => 'editor',
                'course_id' => $courses->random()->id,
                'year_id' => $years->random()->id,
                'department_id' => $departments->random()->id,
            ]));
            $userReviewer = User::firstOrCreate([
                'email' => 'revisor@gmail.com',
            ], array_merge($baseUser, [
                'name' => 'Revisor',
                'role' => 'revisor',
                'course_id' => $courses->random()->id,
                'year_id' => $years->random()->id,
                'department_id' => $departments->random()->id,
            ]));
            $userViewer = User::firstOrCreate([
                'email' => 'leitor@gmail.com',
            ], array_merge($baseUser, [
                'name' => 'Leitor',
                'role' => 'leitor',
                'course_id' => $courses->random()->id,
                'year_id' => $years->random()->id,
                'department_id' => $departments->random()->id,
            ]));

            $userAdmin->assignRole($adminRole);
            $userEditor->assignRole($editorRole);
            $userReviewer->assignRole($reviewerRole);
            $userViewer->assignRole($viewerRole);
        // Informativos de exemplo
        Informativo::factory()->count(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
