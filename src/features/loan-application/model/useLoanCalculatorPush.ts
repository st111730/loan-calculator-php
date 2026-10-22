// Отправка заявки на сервер. true — сервер принял заявку.
// Состояние (pushStatus/pushError/reset) теперь живёт в useCalculatorStore
// (Plan.md 6.4); здесь остался только сам fetch с обработкой ошибок.
import { useCallback } from "react";
import type { LoanApplication } from "@/entities/applicant";
import { useCalculatorStore } from "@/store/useCalculatorStore";

/** Обновляет статус отправки в сторе и возвращает true, если сервер принял заявку. */
export function useLoanCalculatorPush() {
  const push = useCallback(async (application: LoanApplication) => {
    useCalculatorStore.getState().startedPush();
    try {
      const res = await fetch("/api/applications", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(application),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => null);
        const first = body?.issues?.[0]?.message as string | undefined;
        throw new Error(first || `Сервер вернул ошибку (${res.status})`);
      }
      useCalculatorStore.getState().pushSucceeded();
      return true;
    } catch (err) {
      const message = err instanceof TypeError
        ? "Не удалось связаться с сервером. Проверьте соединение."
        : err instanceof Error
          ? err.message
          : "Неизвестная ошибка";
      useCalculatorStore.getState().pushFailed(message);
      return false;
    }
  }, []);

  return { push };
}
