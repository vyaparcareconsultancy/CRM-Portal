// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('CRM Client Registration & Validation E2E Flow', () => {

  test.beforeEach(async ({ page }) => {
    // 1. Visit Login page and authenticate as Admin
    await page.goto('/login');
    await expect(page).toHaveTitle(/Sign In|CRM Portal/);

    await page.fill('#email', 'admin@crm.local');
    await page.fill('#password', 'Admin@123456');
    await page.click('#submitBtn');

    // Wait for redirect to dashboard
    await page.waitForURL(/\/dashboard|\/clients/);
  });

  test('Full multi-step client registration wizard and appearance in clients list', async ({ page }) => {
    // Navigate to Client Registration wizard
    await page.goto('/clients/create');
    await expect(page.locator('h4')).toContainText('New Client Registration');

    const randomSuffix = Math.floor(Math.random() * 90000) + 10000;
    const clientName = `Rohan Verma ${randomSuffix}`;
    const clientEmail = `rohan.${randomSuffix}@example.com`;
    const clientMobile = `98${randomSuffix}123`;

    // -------------------------------------------------------------------------
    // Step 1: Basic Information
    // -------------------------------------------------------------------------
    await page.check('#typeIndividual');
    await page.fill('#clientName', clientName);
    await page.fill('#clientEmail', clientEmail);
    await page.fill('#clientMobile', clientMobile);
    await page.click('#btnNext1');

    // -------------------------------------------------------------------------
    // Step 2: Business Information
    // -------------------------------------------------------------------------
    await expect(page.locator('#step2')).toBeVisible();
    await page.selectOption('#industry', 'IT & Software');
    await page.selectOption('#companySize', '11-50');
    await page.fill('#panNo', 'ABCDE1234F');
    await page.fill('#gstNo', '27ABCDE1234F1Z5');
    await page.click('#btnNext2');

    // -------------------------------------------------------------------------
    // Step 3: Address Details
    // -------------------------------------------------------------------------
    await expect(page.locator('#step3')).toBeVisible();
    await page.fill('#addressLine1', '404 Innovation Hub, BKC');
    await page.fill('#city', 'Mumbai');
    await page.selectOption('#state', 'Maharashtra');
    await page.fill('#pincode', '400051');
    await page.click('#btnNext3');

    // -------------------------------------------------------------------------
    // Step 4: CRM & Documents
    // -------------------------------------------------------------------------
    await expect(page.locator('#step4')).toBeVisible();
    await page.selectOption('#leadSource', 'Website');
    await page.selectOption('#status', 'active');
    await page.check('#consentCheckbox');
    await page.click('#btnNext4');

    // -------------------------------------------------------------------------
    // Step 5: Review & Submit
    // -------------------------------------------------------------------------
    await expect(page.locator('#step5')).toBeVisible();
    await expect(page.locator('#reviewName')).toContainText(clientName);
    await expect(page.locator('#reviewEmail')).toContainText(clientEmail);
    await expect(page.locator('#reviewMobile')).toContainText(clientMobile);

    // Submit form
    await page.click('#btnSubmit');

    // Expect successful submission toast / redirect to client profile or clients list
    await page.waitForURL(/\/clients/);

    // -------------------------------------------------------------------------
    // Verify client appears in DataTables list
    // -------------------------------------------------------------------------
    await page.goto('/clients');
    await expect(page.locator('#clientsTable')).toBeVisible();

    // Search for the newly created client
    const searchInput = page.locator('#clientsTable_filter input, input[type="search"]');
    if (await searchInput.isVisible()) {
      await searchInput.fill(clientName);
    }

    // Verify row appears with client name and email
    await expect(page.locator('#clientsTable')).toContainText(clientName);
    await expect(page.locator('#clientsTable')).toContainText(clientEmail);
  });

  test('Validation errors are properly triggered for invalid mobile and GST', async ({ page }) => {
    await page.goto('/clients/create');

    // 1. Trigger bad mobile validation
    await page.fill('#clientName', 'Test Invalid');
    await page.fill('#clientEmail', 'valid.email@example.com');
    await page.fill('#clientMobile', '12345'); // Invalid: less than 10 digits and does not start with 6-9
    await page.click('#btnNext1');

    // Verify step did not advance and field error is shown
    await expect(page.locator('#step1')).toBeVisible();
    const mobileError = page.locator('#clientMobile ~ .invalid-feedback, #clientMobile.is-invalid');
    await expect(mobileError).toBeVisible();

    // Correct the mobile
    await page.fill('#clientMobile', '9876543210');
    await page.click('#btnNext1');

    // 2. Advance to Step 2 and test bad GST
    await expect(page.locator('#step2')).toBeVisible();
    await page.fill('#gstNo', 'INVALID_GST_PATTERN_99');
    await page.click('#btnNext2');

    // Verify step did not advance and GST error is shown
    await expect(page.locator('#step2')).toBeVisible();
    const gstError = page.locator('#gstNo ~ .invalid-feedback, #gstNo.is-invalid');
    await expect(gstError).toBeVisible();
  });

});
