import { test, expect, type Page } from "@playwright/test";
import { uniqueTestEmail } from "../utils/auth";

async function fillRegistroForm(
  page: Page,
  data: { name: string; email: string; password: string; acceptTerms?: boolean }
) {
  await page.goto("/registro");
  await page.locator('input[name="name"]').fill(data.name);
  await page.locator('input[name="email"]').fill(data.email);
  await page.locator('input[name="password"]').fill(data.password);
  if (data.acceptTerms ?? true) {
    await page.locator('input[type="checkbox"]').check();
  }
}

test.describe("Registro", () => {
  test("crea una cuenta nueva, inicia sesión y redirige al inicio", async ({
    page,
  }) => {
    await fillRegistroForm(page, {
      name: "Docente de Prueba",
      email: uniqueTestEmail(),
      password: "ContraseñaSegura123",
    });
    await page.getByRole("button", { name: "Registrarse" }).click();

    await page.waitForURL("/");
    await expect(page).toHaveURL("/");
  });

  test("rechaza un correo que ya está registrado", async ({ page }) => {
    const email = uniqueTestEmail();

    await fillRegistroForm(page, {
      name: "Docente Uno",
      email,
      password: "ContraseñaSegura123",
    });
    await page.getByRole("button", { name: "Registrarse" }).click();
    await page.waitForURL("/");

    await fillRegistroForm(page, {
      name: "Docente Dos",
      email,
      password: "OtraContraseña123",
    });
    await page.getByRole("button", { name: "Registrarse" }).click();

    await expect(
      page.getByText("Ya existe una cuenta con este correo.")
    ).toBeVisible();
    await expect(page).toHaveURL(/\/registro/);
  });

  test("exige una contraseña de al menos 8 caracteres", async ({ page }) => {
    await page.goto("/registro");
    const password = page.locator('input[name="password"]');
    await password.fill("corta");

    const isValid = await password.evaluate((el: HTMLInputElement) =>
      el.checkValidity()
    );
    expect(isValid).toBe(false);
  });

  test("exige aceptar los términos y condiciones antes de enviar", async ({
    page,
  }) => {
    await fillRegistroForm(page, {
      name: "Docente de Prueba",
      email: uniqueTestEmail(),
      password: "ContraseñaSegura123",
      acceptTerms: false,
    });
    await page.getByRole("button", { name: "Registrarse" }).click();

    await expect(page).toHaveURL(/\/registro/);
    const checkboxValid = await page
      .locator('input[type="checkbox"]')
      .evaluate((el: HTMLInputElement) => el.checkValidity());
    expect(checkboxValid).toBe(false);
  });

  test("enlaza a la página de login", async ({ page }) => {
    await page.goto("/registro");
    await page.getByRole("link", { name: "Inicia sesión aquí" }).click();

    await expect(page).toHaveURL(/\/login/);
  });
});
