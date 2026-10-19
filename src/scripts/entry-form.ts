/* =======================================
 * ストラテジックアライアンス採用 エントリーフォーム制御
 * URL: /src/scripts/entry-form.ts
 * Referenced in: /src/components/forms/EntryForm.astro
 * Created: 2026-10-06
 * Last updated: 2026-10-19
 * ======================================= */

const stepNames: Record<string, string> = {
  "1": "基本情報",
  "2": "経験・スキル",
  "3": "保有資格・職務経歴",
  "4": "自由入力",
};

type FormControl = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

const toHalfWidthDigits = (value: string) =>
  value.replace(/[０-９]/g, (digit) =>
    String.fromCharCode(digit.charCodeAt(0) - 0xfee0),
  );

const isValidBirthDate = (value: string) => {
  const normalized = toHalfWidthDigits(
    value.replace(/^[ \u3000]+|[ \u3000]+$/gu, ""),
  );
  const match =
    /^(\d{4})年(\d{1,2})月(\d{1,2})日$/.exec(normalized) ??
    /^(\d{4})\/(\d{1,2})\/(\d{1,2})$/.exec(normalized) ??
    /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(normalized) ??
    /^(\d{4})(\d{2})(\d{2})$/.exec(normalized);

  if (!match) return false;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const date = new Date(Date.UTC(year, month - 1, day));
  if (
    year < 1000 ||
    date.getUTCFullYear() !== year ||
    date.getUTCMonth() + 1 !== month ||
    date.getUTCDate() !== day
  ) {
    return false;
  }

  const today = Object.fromEntries(
    new Intl.DateTimeFormat("en-US", {
      timeZone: "Asia/Tokyo",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    })
      .formatToParts(new Date())
      .map(({ type, value }) => [type, Number(value)]),
  );

  return (
    year < today.year ||
    (year === today.year && month < today.month) ||
    (year === today.year && month === today.month && day < today.day)
  );
};

const isValidPhoneNumber = (value: string) => {
  if (value.length > 30) return false;

  const normalized = toHalfWidthDigits(value);
  const separators = /[ \u3000\u00a0\t\-\uFF0D\u30FC\uFF70\u2010-\u2015\u2212]/gu;
  if (!/^[0-9 \u3000\u00a0\t\-\uFF0D\u30FC\uFF70\u2010-\u2015\u2212]+$/u.test(normalized)) {
    return false;
  }

  return /^0[0-9]{9,10}$/.test(normalized.replace(separators, ""));
};

const createSubmissionKey = () => {
  if (typeof globalThis.crypto?.randomUUID === "function") {
    return globalThis.crypto.randomUUID();
  }

  const bytes = new Uint8Array(16);
  if (typeof globalThis.crypto?.getRandomValues === "function") {
    globalThis.crypto.getRandomValues(bytes);
  } else {
    for (let index = 0; index < bytes.length; index += 1) {
      bytes[index] = Math.floor(Math.random() * 256);
    }
  }

  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hexadecimal = Array.from(bytes, (byte) =>
    byte.toString(16).padStart(2, "0"),
  ).join("");

  return [
    hexadecimal.slice(0, 8),
    hexadecimal.slice(8, 12),
    hexadecimal.slice(12, 16),
    hexadecimal.slice(16, 20),
    hexadecimal.slice(20),
  ].join("-");
};

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
  const submissionKey = entryForm.querySelector<HTMLInputElement>(
    "[data-entry-submission-key]",
  );
  const resumeInput = entryForm.querySelector<HTMLInputElement>(
    "[data-entry-file-input]",
  );
  const resumeLabel = entryForm.querySelector<HTMLElement>(
    "[data-entry-file-label]",
  );
  const mailWarning = entryForm.querySelector<HTMLElement>(
    "[data-entry-mail-warning]",
  );

  if (!entryHeader || !stepLabel || !progress || !progressBar) return;

  if (submissionKey && !submissionKey.value) {
    submissionKey.value = createSubmissionKey();
  }

  const resetResumeFile = () => {
    if (resumeInput) resumeInput.value = "";
    if (resumeLabel) {
      resumeLabel.textContent = "＋ ファイルを選択（PDF・Word）";
    }
  };

  const validateResumeFile = () => {
    const file = resumeInput?.files?.[0];
    if (!file) return true;

    const extension = file.name.split(".").pop()?.toLowerCase() ?? "";
    if (!["pdf", "doc", "docx"].includes(extension)) {
      resetResumeFile();
      window.alert("PDF・Word形式のファイルを選択してください");
      return false;
    }

    if (file.size > 5 * 1024 * 1024) {
      resetResumeFile();
      window.alert("5MB以下のファイルを選択してください");
      return false;
    }

    return true;
  };

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
        ? field.files[0].name
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
      } else if (field.name === "birthDate" && !isValidBirthDate(field.value)) {
        field.setCustomValidity(
          "生年月日は「1990年1月1日」「1990/1/1」「1990-01-01」「19900101」のいずれかで、過去の実在する日付をご入力ください。",
        );
      } else if (field.name === "phoneNumber" && field.value.length > 30) {
        field.setCustomValidity("電話番号は30文字以内でご入力ください。");
      } else if (field.name === "phoneNumber" && !isValidPhoneNumber(field.value)) {
        field.setCustomValidity(
          "電話番号は数字・ハイフン・空白を使用し、0から始まる10桁または11桁でご入力ください。",
        );
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
    if (isForward && currentStepNumber === 3 && !validateResumeFile()) return;
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
      setSubmitStatus(
        "送信設定を確認できませんでした。時間をおいて再度お試しください。",
        true,
      );
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
        ok?: boolean;
        message?: string;
        field?: string;
        mailWarning?: string;
      } | null;

      if (!response.ok) {
        if (payload?.field === "birthDate" || payload?.field === "phoneNumber") {
          setSubmitStatus("");
          showStep("1");
          const invalidField = entryForm.querySelector<HTMLInputElement>(
            `[name="${payload.field}"]`,
          );
          invalidField?.setCustomValidity(payload.message ?? "入力内容をご確認ください。");
          invalidField?.focus();
          invalidField?.reportValidity();
          return;
        }

        if (payload?.field === "resumeFile") {
          setSubmitStatus("");
          resetResumeFile();
          showStep("3");
          window.alert(
            payload.message ??
              "職務経歴書の保存に失敗しました。再度選び直してください",
          );
          return;
        }

        throw new Error(
          payload?.message ??
            "送信できませんでした。時間をおいて再度お試しください。",
        );
      }

      if (payload?.ok !== true) {
        throw new Error(
          "送信結果を確認できませんでした。時間をおいて再度お試しください。",
        );
      }

      setSubmitStatus("");
      if (mailWarning) {
        mailWarning.textContent = payload?.mailWarning ?? "";
        mailWarning.hidden = !payload?.mailWarning;
      }
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

  resumeInput?.addEventListener("change", (event) => {
    const input = event.currentTarget as HTMLInputElement;

    if (!resumeLabel) return;
    resumeLabel.textContent = input.files?.[0]?.name
      ? input.files[0].name
      : "＋ ファイルを選択（PDF・Word）";
  });
};

document
  .querySelectorAll<HTMLFormElement>("[data-entry-form]")
  .forEach(initializeEntryForm);
