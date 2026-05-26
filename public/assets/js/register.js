// Página de cadastro (sem senha, por código).

// Vibração tátil curta
const haptic = (pattern = 10) => {
  if (navigator.vibrate) navigator.vibrate(pattern);
};

// Ativa/desativa estado de loading em um botão
const setLoading = (btn, loading) => {
  if (!btn) return;
  btn.classList.toggle('is-loading', loading);
  btn.disabled = loading;
};

// Anima um elemento re-disparando a classe auth-step
const animateStep = (el) => {
  if (!el) return;
  el.classList.remove('auth-step');
  void el.offsetWidth;
  el.classList.add('auth-step');
};

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('register-form');
  if (!form) return;

  const nameInput = form.querySelector('[name="name"]');
  const emailInput = form.querySelector('[name="email"]');
  const cpfInput = form.querySelector('[name="cpf"]');
  const whatsappInput = form.querySelector('[name="whatsapp"]');
  const deliveryInputs = Array.from(form.querySelectorAll('input[name="delivery_method"]'));
  const rememberInput = form.querySelector('input[name="remember_me"]');
  const codeInput = form.querySelector('[name="code"]');
  const codeStep = document.getElementById('code-step');
  const submitBtn = document.getElementById('submit-register');
  const verifyBtn = document.getElementById('verify-code');
  const resendBtn = document.getElementById('resend-code');
  const codeStatus = document.getElementById('code-status');
  const message = document.getElementById('register-message');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const basePath = (document.querySelector('meta[name="base-path"]')?.content || '').replace(/\/$/, '');
  let registerCompleted = false;

  const setMessage = (text, isError = false) => {
    if (!message) return;
    message.textContent = text;
    message.classList.toggle('msg-error', !!isError);
    message.classList.toggle('msg-success', !isError && !!text);
  };

  const setHint = (input, text = '') => {
    const hint = input?.closest('.field')?.querySelector('.field-hint');
    if (!hint) return;
    hint.textContent = text;
    if (text) {
      hint.classList.add('is-error');
      input.closest('.field')?.classList.add('is-invalid');
    } else {
      hint.classList.remove('is-error');
      input.closest('.field')?.classList.remove('is-invalid');
    }
  };

  const maskCpf = (value) =>
    value
      .replace(/\D/g, '')
      .slice(0, 11)
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

  const normalizePhone = (value) => (value || '').replace(/\D/g, '').slice(0, 13);
  const isValidPhone = (value) => {
    const digits = normalizePhone(value);
    return digits.startsWith('55') ? digits.length === 13 : digits.length === 11;
  };

  const validateEmail = (email) => /\S+@\S+\.\S+/.test(email);

  cpfInput?.addEventListener('input', (e) => {
    e.target.value = maskCpf(e.target.value);
  });

  whatsappInput?.addEventListener('input', (e) => {
    e.target.value = normalizePhone(e.target.value);
  });

  const validateForm = (checkCode = false) => {
    let ok = true;
    if (!nameInput?.value.trim()) {
      setHint(nameInput, 'Informe seu nome.'); ok = false;
    } else { setHint(nameInput); }
    if (!emailInput?.value.trim() || !validateEmail(emailInput.value)) {
      setHint(emailInput, 'E-mail invalido.'); ok = false;
    } else { setHint(emailInput); }
    if (!whatsappInput?.value.trim() || !isValidPhone(whatsappInput.value)) {
      setHint(whatsappInput, 'Informe um WhatsApp brasileiro com DDD.'); ok = false;
    } else { setHint(whatsappInput); }
    if (!cpfInput?.value.trim()) {
      setHint(cpfInput, 'Informe o CPF.'); ok = false;
    } else { setHint(cpfInput); }
    if (checkCode && !codeInput?.value.trim()) {
      setHint(codeInput, 'Digite o codigo enviado.'); ok = false;
    } else if (codeInput) { setHint(codeInput); }
    return ok;
  };

  const getDeliveryMethod = () => deliveryInputs.find((input) => input.checked)?.value || 'email';
  const deliveryLabel = (method) => (method === 'whatsapp' ? 'WhatsApp' : 'e-mail');

  const postForm = async (url, data) => {
    const body = new URLSearchParams({ ...data, csrf_token: csrfToken });
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    const json = await resp.json().catch(() => ({}));
    if (!resp.ok) throw { ...json, _status: resp.status };
    return json;
  };

  const showCodeStep = () => {
    if (!codeStep) return;
    const fields = document.getElementById('register-fields');
    if (fields) fields.hidden = true;
    codeStep.hidden = false;
    animateStep(codeStep);
    setTimeout(() => codeInput?.focus(), 120);
  };

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (registerCompleted) return;
    if (!validateForm(false)) {
      haptic([10, 50, 10]);
      setMessage('Corrija os campos obrigatorios.', true);
      return;
    }
    setMessage('Enviando cadastro...');
    setLoading(submitBtn, true);
    if (codeStatus) codeStatus.textContent = 'Gerando codigo...';
    try {
      const method = getDeliveryMethod();
      const result = await postForm('api/register.php', {
        name: nameInput.value.trim(),
        email: emailInput.value.trim(),
        cpf: cpfInput.value.trim(),
        whatsapp: normalizePhone(whatsappInput.value.trim()),
        delivery_method: method,
      });
      registerCompleted = true;
      haptic(12);
      setMessage(result.message || 'Cadastro criado. Valide o codigo.');
      if (codeStatus) codeStatus.textContent = `Codigo criado. Verifique seu ${deliveryLabel(method)}.`;
      showCodeStep();
    } catch (err) {
      haptic([10, 50, 10]);
      if (err._status === 409) {
        setMessage(err.error || 'Ja existe uma conta com estes dados. Redirecionando para login...', true);
        setTimeout(() => { window.location.href = `${basePath}/login`; }, 1800);
        return;
      }
      setMessage(err.error || 'Nao foi possivel criar o cadastro.', true);
      if (codeStatus) codeStatus.textContent = 'Falha ao gerar codigo.';
    } finally {
      setLoading(submitBtn, false);
    }
  });

  verifyBtn?.addEventListener('click', async () => {
    if (!registerCompleted) {
      haptic([10, 50, 10]);
      setMessage('Crie a conta antes de validar o codigo.', true);
      return;
    }
    if (!validateForm(true)) {
      haptic([10, 50, 10]);
      setMessage('Digite o codigo recebido.', true);
      return;
    }
    setMessage('Validando codigo...');
    setLoading(verifyBtn, true);
    try {
      const result = await postForm('api/register_verify.php', {
        email: emailInput?.value.trim(),
        cpf: cpfInput?.value.trim(),
        code: codeInput?.value.trim(),
        remember_me: rememberInput?.checked ? '1' : '0',
      });
      haptic([10, 80, 20]);
      setMessage(result.message || 'Cadastro confirmado. Redirecionando...');
      setTimeout(() => { window.location.href = `${basePath}/perfil`; }, 800);
    } catch (err) {
      haptic([10, 50, 10]);
      if (err._status === 409) {
        setMessage(err.error || 'Conta ja verificada. Redirecionando para login...', true);
        setTimeout(() => { window.location.href = `${basePath}/login`; }, 1800);
        return;
      }
      setMessage(err.error || 'Codigo invalido ou expirado.', true);
      setLoading(verifyBtn, false);
    }
  });

  resendBtn?.addEventListener('click', async () => {
    if (!registerCompleted) {
      haptic([10, 50, 10]);
      setMessage('Conclua o cadastro primeiro.', true);
      return;
    }
    setMessage('Reenviando codigo...');
    setLoading(resendBtn, true);
    if (codeStatus) codeStatus.textContent = 'Gerando novo codigo...';
    try {
      const method = getDeliveryMethod();
      const result = await postForm('api/register.php', {
        name: nameInput?.value.trim(),
        email: emailInput?.value.trim(),
        cpf: cpfInput?.value.trim(),
        whatsapp: normalizePhone(whatsappInput?.value.trim()),
        delivery_method: method,
      });
      haptic(12);
      setMessage(result.message || 'Codigo reenviado.');
      if (codeStatus) codeStatus.textContent = `Codigo reenviado. Consulte seu ${deliveryLabel(method)}.`;
    } catch (err) {
      haptic([10, 50, 10]);
      setMessage(err.error || 'Nao foi possivel reenviar o codigo.', true);
      if (codeStatus) codeStatus.textContent = 'Falha ao reenviar.';
    } finally {
      setLoading(resendBtn, false);
    }
  });
});
