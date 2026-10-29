import { expect, test } from "@playwright/test";

const BASE_URL = "http://localhost:5173";

test.beforeEach(async ({ page }) => {
  await page.goto(BASE_URL);
});

test("кнопка «Оставить заявку» неактивна, пока не поставлены обе галочки согласия", async ({
  page,
}) => {
  const submitButton = page.getByRole("button", { name: "Отправить в космос" });
  const citizenCheckbox = page.getByRole("checkbox", { name: /гражданином РФ/ });
  const consentCheckbox = page.getByRole("checkbox", { name: /152-ФЗ/ });

  // По умолчанию форма пустая и чекбоксы сняты — кнопка неактивна
  await expect(citizenCheckbox).not.toBeChecked();
  await expect(consentCheckbox).not.toBeChecked();
  await expect(submitButton).toBeDisabled();

  // Заполняем все поля, но галочки не ставим — кнопка всё ещё неактивна
  await page.getByLabel("ФИО").fill("Петров Пётр Петрович");
  await page.getByLabel("Ежемесячный доход, ₽").fill("600000");
  await expect(submitButton).toBeDisabled();

  // Одна из двух галочек — кнопка по-прежнему неактивна
  await citizenCheckbox.check();
  await expect(citizenCheckbox).toBeChecked();
  await expect(consentCheckbox).not.toBeChecked();
  await expect(submitButton).toBeDisabled();

  // Обе галочки — кнопка становится активной
  await consentCheckbox.check();
  await expect(consentCheckbox).toBeChecked();
  await expect(submitButton).toBeEnabled();
});

test("при изменении срока кредита (ползунка) меняется общая сумма выплат", async ({
  page,
}) => {
  const termSlider = page.getByRole("slider", { name: "Срок кредита слайдером" });
  const totalRepaid =
    page.locator("dt", { hasText: "Общая сумма выплат" }).locator("+ dd");

  await expect(termSlider).toBeVisible();

  const totalBefore = (await totalRepaid.textContent())?.trim();
  expect(totalBefore, "итоговая сумма выплат должна отображаться").toBeTruthy();

  // Двигаем ползунок срока: со 120 мес на 84 мес
  await termSlider.fill("84");

  // Поле срока рассчитывается синхронно
  await expect(page.getByLabel("Срок кредита", { exact: true })).toHaveValue("84");
  await expect(page.getByText("84", { exact: true }).first()).toBeVisible();

  const totalAfter = (await totalRepaid.textContent())?.trim();
  expect(totalAfter).toBeTruthy();
  expect(
    totalAfter,
    "итоговая сумма выплат должна измениться после смены срока"
  ).not.toBe(totalBefore);
  await expect(totalRepaid).not.toHaveText(totalBefore!);
});

test("успешная отправка заявки: появляется зелёная плашка «Заявка принята»", async ({
  page,
}) => {
  const submitButton = page.getByRole("button", { name: "Оставить заявку" });
  await expect(submitButton).toBeDisabled();

  // Заполняем все поля формы
  await page.getByLabel("ФИО").fill("Петров Пётр Петрович");
  await page.getByLabel("Сумма кредита", { exact: true }).fill("5000000");
  await page.getByLabel("Срок кредита", { exact: true }).fill("84");
  await page.getByRole("radio", { name: /Автокредит/ }).check();
  await page.getByLabel("Ежемесячный доход, ₽").fill("600000");

  // Ставим обе галочки согласия
  await page.getByRole("checkbox", { name: /гражданином РФ/ }).check();
  await page.getByRole("checkbox", { name: /152-ФЗ/ }).check();

  await expect(submitButton).toBeEnabled();
  await submitButton.click();

  // Появляется зелёная плашка успеха
  const successPlate = page.getByText(/Заявка на .* принята/);
  await expect(successPlate).toBeVisible();
  await expect(successPlate).toHaveText(
    "Заявка на «Автокредит» принята. Мы свяжемся с вами после рассмотрения."
  );

  // Плашка зелёная: цвет текста = var(--color-success) = #4ADE80
  await expect(successPlate).toHaveCSS("color", "rgb(74, 222, 128)");
});
