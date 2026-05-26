// Pagina de login (sem senha, por codigo).
const Utils = window.IngressoUtils;
const { isValidEmail, normalizeCpf, isValidCpf, basePath, postForm } = Utils;

// Vibração tátil curta (suportada em Android; silenciosa no iOS)
const haptic = (pattern = 10) => {
  if (navigator.vibrate) navigator.vibrate(pattern);
};

// Coloca/tira o botão em estado de carregamento
const setLoading = (btn, loading) => {
  if (!btn) return;
  btn.classList.toggle('is-loading', loading);
  btn.disabled = loading;
};

// Dispara a animação de slide re-adicionando a classe
const animateStep = (el) => {
  if (!el) return;
  el.classList.remove('auth-step');
  // Força reflow para reiniciar a animação
  void el.offsetWidth;
  el.classList.add('auth-step');
};

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const requestBtn = document.getElementById('request-code');
  const messageEl = document.getElementById('login-message');
  const submitBtn = document.getElementById('submit-login');
  const codeStep = document.getElementById('code-step');
  const codeInput = form?.querySelector('input[name="code"]');
  const loginStep = document.getElementById('login-step');
  const identifierInput = form?.querySelector('input[name="identifier"]');
  const rememberInput = form?.querySelector('input[name="remember_me"]');
  const deliveryInputs = Array.from(document.querySelectorAll('input[name="delivery_method"]'));

  if (messageEl && !messageEl.hasAttribute('aria-live')) {
    messageEl.setAttribute('aria-live', 'polite');
  }

  const setMessage = (text, isError = false) => {
    messageEl.textContent = text;
    messageEl.classList.toggle('msg-error', !!isError);
    messageEl.classList.toggle('msg-success', !isError && !!text);
  };

  const updateSubmitAvailability = () => {
    if (!submitBtn) return;
    if (codeInput?.disabled) {
      submitBtn.disabled = true;
      return;
    }
    submitBtn.disabled = !codeInput?.value.trim();
  };

  const hideCodeStep = () => {
    if (codeStep) codeStep.hidden = true;
    if (loginStep) {
      const wasHidden = loginStep.hidden;
      loginStep.hidden = false;
      if (wasHidden) animateStep(loginStep);
    }
    if (codeInput) {
      codeInput.value = '';
      codeInput.disabled = true;
    }
    updateSubmitAvailability();
  };

  const revealCodeStep = () => {
    if (loginStep) loginStep.hidden = true;
    if (codeStep) {
      codeStep.hidden = false;
      animateStep(codeStep);
    }
    if (codeInput) {
      codeInput.disabled = false;
      // Pequeno delay para o teclado abrir após a animação
      setTimeout(() => codeInput.focus(), 120);
    }
    updateSubmitAvailability();
  };

  hideCodeStep();

  const getFormData = () => {
    const formData = new FormData(form);
    const rawId = formData.get('identifier')?.toString().trim() || '';
    const code = formData.get('code')?.toString().trim() || '';
    const deliveryMethod = deliveryInputs.find((input) => input.checked)?.value || 'email';

    if (isValidEmail(rawId)) {
      return { email: rawId, cpf: '', code, identifier: rawId, type: 'email', rawId, deliveryMethod };
    }
    const cpf = normalizeCpf(rawId);
    if (isValidCpf(cpf)) {
      return { email: '', cpf, code, identifier: cpf, type: 'cpf', rawId: cpf, deliveryMethod };
    }
    return { email: '', cpf: '', code, identifier: rawId, type: 'invalid', rawId, deliveryMethod };
  };

  identifierInput?.addEventListener('input', () => {
    hideCodeStep();
  });

  deliveryInputs.forEach((input) => {
    input.addEventListener('change', () => {
      hideCodeStep();
    });
  });

  codeInput?.addEventListener('input', updateSubmitAvailability);

  requestBtn?.addEventListener('click', async () => {
    const { email, cpf, type, rawId, deliveryMethod } = getFormData();
    hideCodeStep();
    if (type === 'invalid') {
      haptic([10, 50, 10]); // vibração de erro
      setMessage('Informe um e-mail ou CPF válido.', true);
      return;
    }
    setMessage('Enviando código...');
    setLoading(requestBtn, true);
    try {
      await postForm('api/login_request_code.php', { identifier: rawId, email, cpf, delivery_method: deliveryMethod });
      haptic(12); // vibração de sucesso
      setMessage(`Código enviado via ${deliveryMethod === 'whatsapp' ? 'WhatsApp' : 'e-mail'}.`);
      revealCodeStep();
    } catch (err) {
      haptic([10, 50, 10]); // vibração de erro
      setMessage(err.error || 'Não foi possível enviar o código.', true);
      hideCodeStep();
    } finally {
      setLoading(requestBtn, false);
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const { email, cpf, code, type, rawId, deliveryMethod } = getFormData();
    if (type === 'invalid') {
      haptic([10, 50, 10]);
      setMessage('Informe um e-mail ou CPF válido.', true);
      return;
    }
    if (codeInput?.disabled) {
      setMessage('Solicite o código para continuar.', true);
      return;
    }
    if (!code) {
      haptic([10, 50, 10]);
      setMessage('Código obrigatório.', true);
      updateSubmitAvailability();
      return;
    }
    setMessage('Validando código...');
    setLoading(submitBtn, true);
    try {
      await postForm('api/login_verify.php', {
        identifier: rawId,
        email,
        cpf,
        code,
        delivery_method: deliveryMethod,
        remember_me: rememberInput?.checked ? '1' : '0',
      });
      haptic([10, 80, 20]); // vibração de login concluído
      setMessage('Login realizado. Redirecionando...');
      setTimeout(() => {
        window.location.href = `${basePath()}/perfil`;
      }, 600);
    } catch (err) {
      haptic([10, 50, 10]);
      setMessage(err.error || 'Não foi possível validar o código.', true);
      setLoading(submitBtn, false);
    }
  });
});
