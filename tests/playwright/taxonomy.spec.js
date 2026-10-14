const { test, expect } = require('@playwright/test');
const { logIn } = require('./session');

const expectRowNames = async (page, tableName, names) => {
  const rows = page.getByRole('table', { name: tableName }).locator('tbody tr');
  await expect.poll(
    () => rows.evaluateAll((elements) => elements.map((row) => row.querySelector('a').textContent.trim()))
  ).toEqual(names);
};

test('an editor reorders the top-level terms with the keyboard', async ({ page }) => {
  await logIn(page, 'editor@example.com');
  await page.goto('/admin/taxonomy');
  await expectRowNames(page, 'Top-level taxonomy terms', ['Alpha', 'Beta', 'Gamma']);

  await page.getByRole('button', { name: 'Reorder Gamma' }).focus();
  await page.keyboard.press('Space');
  await page.keyboard.press('ArrowUp');
  await page.keyboard.press('Space');
  await expect(page.getByText('Moved Gamma to position 2 of 3')).toBeVisible();
  await expectRowNames(page, 'Top-level taxonomy terms', ['Alpha', 'Gamma', 'Beta']);

  await page.reload();
  await expectRowNames(page, 'Top-level taxonomy terms', ['Alpha', 'Gamma', 'Beta']);
});

test('an administrator creates a type, then an editor creates a term of that type and a child term', async ({ page }) => {
  await logIn(page, 'admin@example.com');
  await page.goto('/admin/taxonomy/types');
  await page.getByRole('link', { name: 'Add type' }).click();
  await page.getByLabel('Name', { exact: true }).fill('Food');
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByText('Created "Food"')).toBeVisible();
  await expect(page).toHaveURL(/\/admin\/taxonomy\/types\/\d+$/);

  await page.context().clearCookies();
  await logIn(page, 'editor@example.com');
  await page.goto('/admin/taxonomy');
  await page.getByRole('link', { name: 'Add term' }).click();
  await page.getByLabel('Name', { exact: true }).fill('Fruit');
  await page.locator('#Form_TaxonomyForm_TypeID').getByRole('combobox').click();
  await page.getByRole('option', { name: 'Food' }).click();
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByText('Created "Fruit"')).toBeVisible();
  await expect(page).toHaveURL(/\/admin\/taxonomy\/terms\/\d+$/);

  await page.getByRole('link', { name: 'Add child term' }).click();
  await page.getByLabel('Name', { exact: true }).fill('Apple');
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByText('Created "Apple"')).toBeVisible();

  await page.goto('/admin/taxonomy');
  await expectRowNames(page, 'Top-level taxonomy terms', ['Alpha', 'Gamma', 'Beta', 'Fruit']);
  await expect(page.getByRole('row', { name: /Fruit/ })).toContainText('Food');
  await page.getByRole('link', { name: 'Fruit' }).click();
  await expectRowNames(page, 'Child terms of Fruit', ['Apple']);
});
