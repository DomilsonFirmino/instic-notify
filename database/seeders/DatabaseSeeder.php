<?php

namespace Database\Seeders;

use App\Models\User;
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
                'informativo.create', 'informativo.edit_any', 'informativo.publish', 'informativo.unpublish',
                'informativo.delete', 'informativo.approve', 'informativo.reject', 'informativo.restore_version',
                'informativo.manage_attachments',
                'estrutura.categoria.manage', 'estrutura.departamento.manage', 'estrutura.curso.manage', 'estrutura.ano_academico.manage',
                'auditoria.logs.view', 'auditoria.historico.view', 'auditoria.ciclo_vida.view',
            ];
            $editorPermissions = [
                'informativo.create_own', 'informativo.edit_own', 'informativo.submit_for_review',
                'informativo.attach_documents', 'informativo.set_dates', 'informativo.view_own_history',
            ];
            $reviewerPermissions = [
                'informativo.list_pending', 'informativo.review', 'informativo.approve_review',
                'informativo.reject_review', 'informativo.view_logs', 'informativo.view_versions',
            ];
            $viewerPermissions = [
                'informativo.view_published', 'informativo.filter', 'informativo.download_attachments',
                'informativo.receive_notifications', 'informativo.subscribe_categories',
            ];

            $adminRole->syncPermissions($adminPermissions);
            $editorRole->syncPermissions($editorPermissions);
            $reviewerRole->syncPermissions($reviewerPermissions);
            $viewerRole->syncPermissions($viewerPermissions);

            // Usuários de exemplo
            $userAdmin = User::firstOrCreate([
                'email' => 'admin@gmail.com',
            ], [
                'name' => 'Admin',
                'password' => bcrypt('password123'),
            ]);
            $userEditor = User::firstOrCreate([
                'email' => 'editor@gmail.com',
            ], [
                'name' => 'Editor',
                'password' => bcrypt('password123'),
            ]);
            $userReviewer = User::firstOrCreate([
                'email' => 'revisor@gmail.com',
            ], [
                'name' => 'Revisor',
                'password' => bcrypt('password123'),
            ]);
            $userViewer = User::firstOrCreate([
                'email' => 'leitor@gmail.com',
            ], [
                'name' => 'Leitor',
                'password' => bcrypt('password123'),
            ]);

            $userAdmin->assignRole($adminRole);
            $userEditor->assignRole($editorRole);
            $userReviewer->assignRole($reviewerRole);
            $userViewer->assignRole($viewerRole);
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
