/* =======================================
 * ストラテジックアライアンス採用 エントリーフォーム制御
 * URL: /src/scripts/entry-form.ts
 * Referenced in: /src/components/forms/EntryForm.astro
 * Created: 2026-10-06
 * Last updated: 2026-10-06
 * ======================================= */

const stepNames: Record<string, string> = {
  "1": "基本情報",
  "2": "経験・スキル",
  "3": "保有資格・職務経歴",
  "4": "自由入力",
};

type FormControl = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

const initializeEntryForm = (entryForm: HTMLFormElement) => {
  const entryHeader = entryForm.querySelector<HTMLElement>(
    "[data-entry-header]",
  );
  const stepLabel = entryForm.querySelector<HTMLElement>(
    "[data-entry-step-label]",
  );
  const progress = entryForm.querySelector<HTMLElement>("[role='progressbar']");
  const progressBar = entryForm.querySelector<HTMLElement>(
    "[data-entry-progress-bar]",
  );
  const submitButton = entryForm.querySelector<HTMLButtonElement>(
    "[data-entry-submit]",
  );
  const submitStatus = entryForm.querySelector<HTMLElement>(
    "[data-entry-submit-status]",
  );

  if (!entryHeader || !stepLabel || !progress || !progressBar) return;

  const getFieldValue = (fieldName: string) => {
    const fields = Array.from(
      entryForm.querySelectorAll<
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
      >(`[name="${fieldName}"]`),
    );
    const field = fields.find((item) => {
      if (!(item instanceof HTMLInputElement)) return true;
      if (item.type !== "radio" && item.type !== "checkbox") return true;
      return item.checked;
    });

    if (!field) return "";

    if (field instanceof HTMLSelectElement) {
      return field.selectedOptions[0]?.textContent?.trim() ?? "";
    }

    if (field instanceof HTMLInputElement && field.type === "file") {
      return field.files?.[0]?.name
        ? `${field.files[0].name} アップロード済み`
        : "未選択";
    }

    return field.value.trim();
  };

  const updateConfirmation = () => {
    entryForm
      .querySelectorAll<HTMLElement>("[data-entry-confirm]")
      .forEach((item) => {
        const fieldName = item.dataset.entryConfirm;
        if (!fieldName) return;

        const value = getFieldValue(fieldName);
        item.textContent = value || "未入力";
      });
  };

  const setSubmitStatus = (message: string, isError = false) => {
    if (!submitStatus) return;

    submitStatus.textContent = message;
    submitStatus.hidden = !message;

    if (isError) {
      submitStatus.dataset.state = "error";
      return;
    }

    delete submitStatus.dataset.state;
  };

  const getFirstInvalidField = (container: ParentNode) => {
    const fields = Array.from(
      container.querySelectorAll<FormControl>("input, select, textarea"),
    );

    return fields.find((field) => {
      field.setCustomValidity("");

      if (field.required && field.value.trim() === "") {
        field.setCustomValidity("この項目を入力してください。");
      }

      return !field.checkValidity();
    });
  };

  const validateStep = (step: HTMLElement) => {
    const invalidField = getFirstInvalidField(step);
    if (!invalidField) return true;

    invalidField.focus();
    invalidField.reportValidity();
    return false;
  };

  const validateAllSteps = () => {
    const steps = Array.from(
      entryForm.querySelectorAll<HTMLElement>(
        '[data-entry-step="1"], [data-entry-step="2"], [data-entry-step="3"], [data-entry-step="4"]',
      ),
    );

    for (const step of steps) {
      const invalidField = getFirstInvalidField(step);
      if (!invalidField) continue;

      showStep(step.dataset.entryStep ?? "1");
      invalidField.focus();
      invalidField.reportValidity();
      return false;
    }

    return true;
  };

  const showStep = (stepNumber: string) => {
    const targetStep = entryForm.querySelector<HTMLElement>(
      `[data-entry-step="${stepNumber}"]`,
    );

    if (!targetStep) return;

    if (stepNumber === "confirm") {
      updateConfirmation();
    }

    entryForm
      .querySelectorAll<HTMLElement>("[data-entry-step]")
      .forEach((step) => {
        step.hidden = step !== targetStep;
      });

    const isComplete = stepNumber === "complete";
    entryHeader.hidden = isComplete;

    if (!isComplete) {
      stepLabel.textContent =
        stepNumber === "confirm"
          ? "入力内容の確認"
          : `Step ${stepNumber} / 4：${stepNames[stepNumber]}`;
      progress.setAttribute(
        "aria-valuenow",
        stepNumber === "confirm" ? "4" : stepNumber,
      );
      progressBar.style.width =
        stepNumber === "confirm" ? "100%" : `${Number(stepNumber) * 25}%`;
    }

    entryForm.scrollIntoView({ block: "start" });
    targetStep.querySelector<HTMLElement>("h1")?.focus({ preventScroll: true });
  };

  entryForm.addEventListener("submit", (event) => {
    event.preventDefault();
  });

  entryForm.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>(
      "[data-entry-go]",
    );

    if (!button) return;

    const currentStep = button.closest<HTMLElement>("[data-entry-step]");
    const currentStepNumber = Number(currentStep?.dataset.entryStep);
    const targetStep = button.dataset.entryGo ?? "1";
    const targetStepNumber = targetStep === "confirm" ? 5 : Number(targetStep);
    const isForward =
      currentStep &&
      Number.isFinite(currentStepNumber) &&
      Number.isFinite(targetStepNumber) &&
      targetStepNumber > currentStepNumber;

    if (isForward && !validateStep(currentStep)) return;
    showStep(targetStep);
  });

  entryForm.addEventListener("input", (event) => {
    const field = event.target;
    if (
      field instanceof HTMLInputElement ||
      field instanceof HTMLSelectElement ||
      field instanceof HTMLTextAreaElement
    ) {
      field.setCustomValidity("");
    }
  });

  submitButton?.addEventListener("click", async () => {
    if (submitButton.disabled) return;
    if (!validateAllSteps()) return;

    const endpoint = entryForm.dataset.entryEndpoint?.trim();

    if (!endpoint) {
      showStep("complete");
      return;
    }

    const originalLabel = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = "送信中…";
    setSubmitStatus("応募内容を送信しています。");

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          Accept: "application/json",
        },
        body: new FormData(entryForm),
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: string;
      } | null;

      if (!response.ok) {
        throw new Error(
          payload?.message ??
            "送信できませんでした。時間をおいて再度お試しください。",
        );
      }

      setSubmitStatus("");
      showStep("complete");
    } catch (error) {
      const message =
        error instanceof Error
          ? error.message
          : "送信できませんでした。時間をおいて再度お試しください。";
      setSubmitStatus(message, true);
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = originalLabel;
    }
  });

  entryForm
    .querySelector<HTMLInputElement>("[data-entry-file-input]")
    ?.addEventListener("change", (event) => {
      const input = event.currentTarget as HTMLInputElement;
      const fileLabel = entryForm.querySelector<HTMLElement>(
        "[data-entry-file-label]",
      );

      if (!fileLabel) return;
      fileLabel.textContent = input.files?.[0]?.name
        ? input.files[0].name
        : "＋ ファイルを選択（PDF・Word）";
    });
};

document
  .querySelectorAll<HTMLFormElement>("[data-entry-form]")
  .forEach(initializeEntryForm);
