# Relatório de Checagem de Roles e Permissions

## Introdução

Este relatório apresenta uma análise detalhada sobre a aplicação e reforço de roles e permissions no projeto, conforme definido no documento "Roles e permissions.pdf". Foram verificados controllers, policies, rotas e uso de middlewares.

---

## 1. Resumo da Implementação Atual

### a) Middleware nas Rotas

-   O arquivo [routes/api_V1.php](routes/api_V1.php) utiliza o middleware `role` para restringir acesso a rotas sensíveis (ex: criação, atualização e remoção de usuários, categorias, departamentos, cursos, anos e informativos).
-   Rotas de leitura (index/show) são públicas para usuários autenticados, enquanto ações de escrita são restritas por role.

### b) Controllers

-   **UserController**: Verificações explícitas de role (admin, leitor) para listagem, visualização, atualização e remoção de usuários.
-   **InformativoController**: Verificações detalhadas de role (admin, revisor, autor) e permissões (ex: `can('informativos.review')`) para cada ação (criar, editar, agendar, aprovar, rejeitar, solicitar revisão, remover).

### c) Policies

-   **UserPolicy**: Define regras para visualizar, criar, atualizar e deletar usuários, usando `hasRole` e `can`.
-   **InformativoPolicy**: Define regras para criar e transicionar status de informativos, usando permissões específicas e checagem de autoria.

### d) Modelos

-   O modelo `User` utiliza o trait `HasRoles` da Spatie, permitindo uso de `hasRole`, `can` e outros métodos de checagem de permissões.

---

## 2. Pontos Onde NÃO Estão a Ser Aplicados ou Podem Ser Melhorados

### a) Falta de Uso de Policies nas Rotas/Controllers

-   **UserController** e **InformativoController** fazem checagens manuais de role/permissão, mas não utilizam diretamente as policies via métodos como `$this->authorize()` ou middleware `can:` nas rotas. Isso pode levar a inconsistências se as regras mudarem apenas nas policies.
-   **Sugestão**: Usar policies diretamente nos controllers (ex: `$this->authorize('update', $user)`) ou middleware `can:` nas rotas para centralizar a lógica de autorização.

### b) Rotas de Favoritos e Notificações

-   As rotas de favoritos e notificações ([UserController](app/Http/Controllers/V1/UserController.php)) não possuem checagem explícita de role/permissão. Usuários podem acessar favoritos/notificações de outros se souberem o ID.
-   **Risco**: Possível exposição de dados de outros usuários.
-   **Sugestão**: Adicionar checagem de ownership ou policy para garantir que apenas o próprio usuário (ou admin) possa acessar esses dados.

### c) Falta de Middleware/Policy em Algumas Rotas

-   Algumas rotas de leitura (ex: `GET /users/{id}`) permitem acesso a qualquer usuário autenticado, mas a checagem de permissão está apenas no controller. Se a lógica mudar, pode haver inconsistência.
-   **Sugestão**: Usar middleware `can:` ou policies para garantir consistência.

### d) Permissões Granulares

-   O uso de permissões como `informativos.review`, `user.create`, etc., está presente nas policies, mas nem sempre reforçado nas rotas/controllers.
-   **Sugestão**: Garantir que todas as permissões necessárias estejam mapeadas e aplicadas tanto nas rotas quanto nas policies.

---

## 3. Conclusão

-   **A maioria das regras de roles e permissions está implementada e reforçada, mas há dependência de checagens manuais em controllers.**
-   **Adoção de policies e middleware `can:` nas rotas aumentaria a robustez e centralização da lógica de autorização.**
-   **Rotas de favoritos e notificações precisam de reforço de ownership/policy.**

---

## 4. Referências

-   [routes/api_V1.php](routes/api_V1.php)
-   [app/Http/Controllers/V1/UserController.php](app/Http/Controllers/V1/UserController.php)
-   [app/Http/Controllers/Api/V1/InformativoController.php](app/Http/Controllers/Api/V1/InformativoController.php)
-   [app/Policies/UserPolicy.php](app/Policies/UserPolicy.php)
-   [app/Policies/InformativoPolicy.php](app/Policies/InformativoPolicy.php)
-   [app/Models/User.php](app/Models/User.php)

---

**Este relatório pode ser atualizado conforme novas regras de roles/permissions sejam adicionadas ou alteradas.**
