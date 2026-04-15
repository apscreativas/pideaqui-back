<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'role' => 'admin']);

        return [$user, $restaurant];
    }

    private function operator(Restaurant $r): User
    {
        return User::factory()->create(['restaurant_id' => $r->id, 'role' => 'operator']);
    }

    private function category(Restaurant $r, array $attrs = []): ExpenseCategory
    {
        return ExpenseCategory::factory()->create(array_merge(['restaurant_id' => $r->id], $attrs));
    }

    private function subcategory(ExpenseCategory $cat, array $attrs = []): ExpenseSubcategory
    {
        return ExpenseSubcategory::factory()->create(array_merge(['expense_category_id' => $cat->id], $attrs));
    }

    private function branch(Restaurant $r, array $attrs = []): Branch
    {
        return Branch::factory()->create(array_merge(['restaurant_id' => $r->id, 'is_active' => true], $attrs));
    }

    // ─── Auth / role ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_expenses(): void
    {
        $this->get(route('expenses.index'))->assertRedirect(route('login'));
    }

    public function test_operator_cannot_access_expenses(): void
    {
        [$_, $r] = $this->admin();
        $op = $this->operator($r);

        $this->actingAs($op)->get(route('expenses.index'))->assertStatus(403);
    }

    public function test_admin_can_view_expenses_index(): void
    {
        [$admin] = $this->admin();

        $this->withoutVite()->actingAs($admin)->get(route('expenses.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($p) => $p
                ->component('Expenses/Index')
                ->has('expenses')
                ->has('categories')
                ->has('totals.count')
                ->has('totals.total')
            );
    }

    public function test_operator_cannot_manage_expense_categories(): void
    {
        [$_, $r] = $this->admin();
        $op = $this->operator($r);

        $this->actingAs($op)->get(route('expense-categories.index'))->assertStatus(403);
    }

    // ─── Create expense ──────────────────────────────────────────────────────

    public function test_admin_can_create_expense(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'Compra de aceite',
            'description' => 'Mayorista',
            'amount' => 450.00,
            'expense_date' => now()->toDateString(),
            'branch_id' => $b->id,
            'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'restaurant_id' => $r->id,
            'branch_id' => $b->id,
            'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id,
            'title' => 'Compra de aceite',
            'amount' => '450.00',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_branch_required_and_must_belong_to_restaurant(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $other = Restaurant::factory()->create();
        $foreignBranch = $this->branch($other);

        // Missing branch_id
        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'X', 'amount' => 50, 'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id, 'expense_subcategory_id' => $sub->id,
        ])->assertSessionHasErrors('branch_id');

        // Branch from another restaurant
        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'X', 'amount' => 50, 'expense_date' => now()->toDateString(),
            'branch_id' => $foreignBranch->id,
            'expense_category_id' => $cat->id, 'expense_subcategory_id' => $sub->id,
        ])->assertSessionHasErrors('branch_id');

        $this->assertEquals(0, Expense::count());
    }

    public function test_subcategory_must_belong_to_selected_category(): void
    {
        [$admin, $r] = $this->admin();
        $catA = $this->category($r, ['name' => 'Insumos']);
        $catB = $this->category($r, ['name' => 'Servicios']);
        $subOfB = $this->subcategory($catB);
        $b = $this->branch($r);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'X',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
            'branch_id' => $b->id,
            'expense_category_id' => $catA->id,
            'expense_subcategory_id' => $subOfB->id,
        ])->assertSessionHasErrors('expense_subcategory_id');

        $this->assertEquals(0, Expense::count());
    }

    public function test_future_expense_date_rejected(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'X', 'amount' => 50, 'expense_date' => now()->addDays(2)->toDateString(),
            'branch_id' => $b->id,
            'expense_category_id' => $cat->id, 'expense_subcategory_id' => $sub->id,
        ])->assertSessionHasErrors('expense_date');
    }

    public function test_inactive_category_is_rejected_on_create(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r, ['is_active' => false]);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'X', 'amount' => 50, 'expense_date' => now()->toDateString(),
            'branch_id' => $b->id,
            'expense_category_id' => $cat->id, 'expense_subcategory_id' => $sub->id,
        ])->assertSessionHasErrors('expense_category_id');
    }

    public function test_expense_with_attachments_persists_files(): void
    {
        Storage::fake(config('filesystems.media_disk', 'public'));
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'Factura luz', 'amount' => 800.00,
            'expense_date' => now()->toDateString(),
            'branch_id' => $b->id,
            'expense_category_id' => $cat->id, 'expense_subcategory_id' => $sub->id,
            'attachments' => [
                UploadedFile::fake()->image('recibo.jpg'),
                UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $expense = Expense::first();
        $this->assertEquals(2, $expense->attachments()->count());
    }

    // ─── Update / delete ─────────────────────────────────────────────────────

    public function test_admin_can_update_expense(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);
        $expense = Expense::factory()->create([
            'restaurant_id' => $r->id, 'branch_id' => $b->id, 'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id, 'created_by_user_id' => $admin->id,
            'amount' => 100,
        ]);

        $this->actingAs($admin)->put(route('expenses.update', $expense), [
            'title' => 'Nuevo título', 'amount' => 250.00,
            'expense_date' => now()->toDateString(),
            'branch_id' => $b->id,
            'expense_category_id' => $cat->id, 'expense_subcategory_id' => $sub->id,
        ])->assertRedirect();

        $this->assertEquals('250.00', $expense->fresh()->amount);
        $this->assertEquals('Nuevo título', $expense->fresh()->title);
    }

    public function test_admin_can_delete_expense(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);
        $expense = Expense::factory()->create([
            'restaurant_id' => $r->id, 'branch_id' => $b->id, 'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id, 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('expenses.destroy', $expense))->assertRedirect();

        $this->assertEquals(0, Expense::count());
    }

    // ─── Categories admin ───────────────────────────────────────────────────

    public function test_admin_can_create_category(): void
    {
        [$admin, $r] = $this->admin();

        $this->actingAs($admin)->post(route('expense-categories.store'), [
            'name' => 'Renta',
        ])->assertRedirect();

        $this->assertDatabaseHas('expense_categories', ['restaurant_id' => $r->id, 'name' => 'Renta']);
    }

    public function test_admin_can_add_subcategory_to_category(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);

        $this->actingAs($admin)->post(route('expense-subcategories.store', $cat), [
            'name' => 'Carne',
        ])->assertRedirect();

        $this->assertDatabaseHas('expense_subcategories', [
            'expense_category_id' => $cat->id, 'name' => 'Carne',
        ]);
    }

    public function test_category_with_expenses_cannot_be_deleted(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);
        Expense::factory()->create([
            'restaurant_id' => $r->id, 'branch_id' => $b->id, 'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id, 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('expense-categories.destroy', $cat))
            ->assertSessionHasErrors('category');

        $this->assertNotNull(ExpenseCategory::find($cat->id));
    }

    public function test_empty_category_can_be_deleted(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);

        $this->actingAs($admin)->delete(route('expense-categories.destroy', $cat))->assertRedirect();

        $this->assertNull(ExpenseCategory::find($cat->id));
    }

    public function test_subcategory_with_expenses_cannot_be_deleted(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);
        Expense::factory()->create([
            'restaurant_id' => $r->id, 'branch_id' => $b->id, 'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id, 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('expense-subcategories.destroy', $sub))
            ->assertSessionHasErrors('subcategory');
    }

    public function test_toggle_category_flips_is_active(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r, ['is_active' => true]);

        $this->actingAs($admin)->patch(route('expense-categories.toggle', $cat))->assertRedirect();

        $this->assertFalse($cat->fresh()->is_active);
    }

    // ─── Tenant isolation ───────────────────────────────────────────────────

    public function test_admin_cannot_view_expense_from_other_restaurant(): void
    {
        [$admin] = $this->admin();
        $other = Restaurant::factory()->create();
        $cat = $this->category($other);
        $sub = $this->subcategory($cat);
        $b = $this->branch($other);
        $expense = Expense::factory()->create([
            'restaurant_id' => $other->id, 'branch_id' => $b->id, 'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id,
            'created_by_user_id' => User::factory()->create(['restaurant_id' => $other->id])->id,
        ]);

        $this->actingAs($admin)->get(route('expenses.show', $expense))->assertStatus(404);
    }

    public function test_attachment_url_and_type_flags_are_exposed_to_frontend(): void
    {
        Storage::fake(config('filesystems.media_disk', 'public'));
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'title' => 'Con adjuntos', 'amount' => 100,
            'expense_date' => now()->toDateString(),
            'branch_id' => $b->id,
            'expense_category_id' => $cat->id, 'expense_subcategory_id' => $sub->id,
            'attachments' => [
                UploadedFile::fake()->image('foto.png'),
                UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf'),
            ],
        ])->assertRedirect();

        $expense = Expense::first();

        $response = $this->withoutVite()->actingAs($admin)->get(route('expenses.show', $expense));

        $response->assertInertia(fn ($page) => $page
            ->component('Expenses/Show')
            ->has('expense.attachments', 2)
            ->has('expense.attachments.0.url')
            ->has('expense.attachments.1.url')
            ->where('expense.attachments.0.is_image', true)
            ->where('expense.attachments.0.is_pdf', false)
            ->where('expense.attachments.1.is_image', false)
            ->where('expense.attachments.1.is_pdf', true)
        );
    }

    public function test_index_lists_only_own_restaurant_expenses(): void
    {
        [$admin, $r] = $this->admin();
        $cat = $this->category($r);
        $sub = $this->subcategory($cat);
        $b = $this->branch($r);
        $other = Restaurant::factory()->create();
        $catOther = $this->category($other);
        $subOther = $this->subcategory($catOther);
        $bOther = $this->branch($other);

        Expense::factory()->create([
            'restaurant_id' => $r->id, 'branch_id' => $b->id, 'expense_category_id' => $cat->id,
            'expense_subcategory_id' => $sub->id, 'created_by_user_id' => $admin->id,
            'expense_date' => now()->toDateString(),
        ]);
        Expense::factory()->create([
            'restaurant_id' => $other->id, 'branch_id' => $bOther->id, 'expense_category_id' => $catOther->id,
            'expense_subcategory_id' => $subOther->id,
            'created_by_user_id' => User::factory()->create(['restaurant_id' => $other->id])->id,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('expenses.index'));

        $response->assertInertia(fn ($p) => $p->has('expenses', 1));
    }
}
