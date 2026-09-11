/**
 * SISTEMA CANECAS - INTERATIVIDADE MODERNA
 * Funcionalidades: animações, tooltips, confirmações, feedback visual
 */

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
  initBootstrap();
  initAnimations();
  initConfirmations();
  initFormValidation();
  initTableEnhancements();
  console.log('✨ Sistema Canecas carregado com sucesso!');
});

// ============================================================================
// BOOTSTRAP COMPONENTS
// ============================================================================

function initBootstrap() {
  // Inicializa todos os tooltips
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );
  tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Inicializa popovers se existirem
  const popoverTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="popover"]')
  );
  popoverTriggerList.map(function(popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
  });
}

// ============================================================================
// ANIMAÇÕES DE ENTRADA
// ============================================================================

function initAnimations() {
  // Anima cards quando entram na viewport
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
          setTimeout(() => {
            entry.target.classList.add('slide-in-up');
          }, index * 50); // Delay progressivo
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.1 }
  );

  document.querySelectorAll('.card, .table-container').forEach((el) => {
    observer.observe(el);
  });
}

// ============================================================================
// CONFIRMAÇÕES ELEGANTES
// ============================================================================

function initConfirmations() {
  // Confirmação para botões de perigo
  document.querySelectorAll('.btn-danger, button[type="submit"].btn-danger').forEach((btn) => {
    btn.addEventListener('click', function(e) {
      const form = this.closest('form');
      const message = this.dataset.confirm || 'Tem certeza que deseja excluir?';
      
      if (!confirm(message)) {
        e.preventDefault();
        return false;
      }
    });
  });

  // Confirmação para logout
  const logoutLink = document.querySelector('a[href*="logout=1"]');
  if (logoutLink) {
    logoutLink.addEventListener('click', function(e) {
      if (!confirm('Deseja realmente sair do sistema?')) {
        e.preventDefault();
      }
    });
  }
}

// ============================================================================
// VALIDAÇÃO DE FORMULÁRIOS
// ============================================================================

function initFormValidation() {
  const forms = document.querySelectorAll('form');
  
  forms.forEach((form) => {
    form.addEventListener('submit', function(e) {
      // Valida campos obrigatórios
      const requiredInputs = form.querySelectorAll('[required]');
      let hasErrors = false;

      requiredInputs.forEach((input) => {
        if (!input.value.trim()) {
          input.style.borderColor = 'var(--danger)';
          hasErrors = true;
        } else {
          input.style.borderColor = '';
        }
      });

      if (hasErrors) {
        e.preventDefault();
        showNotification('Por favor, preencha todos os campos obrigatórios.', 'danger');
      }
    });

    // Remove erro ao digitar
    form.querySelectorAll('input, select, textarea').forEach((input) => {
      input.addEventListener('input', function() {
        this.style.borderColor = '';
      });
    });
  });
}

// ============================================================================
// MELHORIAS EM TABELAS
// ============================================================================

function initTableEnhancements() {
  // Adiciona classe hover nas linhas
  document.querySelectorAll('.table tbody tr').forEach((row) => {
    row.addEventListener('mouseenter', function() {
      this.style.cursor = 'pointer';
    });
  });

  // Torna células de status clicáveis
  document.querySelectorAll('.status').forEach((status) => {
    status.style.cursor = 'pointer';
    status.title = 'Status: ' + status.textContent.trim();
  });
}

// ============================================================================
// NOTIFICAÇÕES
// ============================================================================

function showNotification(message, type = 'ok') {
  const notification = document.createElement('div');
  notification.className = type;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 2rem;
    right: 2rem;
    z-index: 9999;
    max-width: 400px;
    box-shadow: var(--shadow-xl);
  `;

  document.body.appendChild(notification);

  // Remove após 5 segundos
  setTimeout(() => {
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-20px)';
    setTimeout(() => notification.remove(), 300);
  }, 5000);
}

// ============================================================================
// LOADING STATE
// ============================================================================

function showLoading(button) {
  if (!button) return;
  
  button.disabled = true;
  button.dataset.originalText = button.innerHTML;
  button.innerHTML = '<span class="spinner"></span> Carregando...';
}

function hideLoading(button) {
  if (!button) return;
  
  button.disabled = false;
  button.innerHTML = button.dataset.originalText || 'Enviar';
}

// Adiciona loading aos formulários
document.querySelectorAll('form').forEach((form) => {
  form.addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn && !submitBtn.classList.contains('btn-danger')) {
      showLoading(submitBtn);
    }
  });
});

// ============================================================================
// TEMA - Smooth Transition
// ============================================================================

// Melhora a transição de tema
const originalToggleTheme = window.toggleTheme;
if (originalToggleTheme) {
  window.toggleTheme = function() {
    document.body.style.transition = 'all 0.3s ease';
    originalToggleTheme();
    setTimeout(() => {
      document.body.style.transition = '';
    }, 300);
  };
}

// ============================================================================
// UTILITÁRIOS
// ============================================================================

// Formata valores monetários
function formatCurrency(value) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(value);
}

// Formata datas
function formatDate(date) {
  return new Intl.DateTimeFormat('pt-BR').format(new Date(date));
}

// Debounce para search inputs
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// ============================================================================
// KEYBOARD SHORTCUTS
// ============================================================================

document.addEventListener('keydown', function(e) {
  // Ctrl/Cmd + K = Focus search
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    const searchInput = document.querySelector('input[type="search"], input[type="text"]');
    if (searchInput) searchInput.focus();
  }

  // Ctrl/Cmd + N = Nova venda (se estiver na página de vendas)
  if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
    const currentPage = window.location.pathname;
    if (currentPage.includes('vendas.php')) {
      e.preventDefault();
      window.location.href = 'vendas_nova.php';
    }
  }

  // Esc = Limpa focus
  if (e.key === 'Escape') {
    document.activeElement?.blur();
  }
});

// ============================================================================
// EXPORTAÇÕES (se necessário)
// ============================================================================

window.appUtils = {
  showNotification,
  showLoading,
  hideLoading,
  formatCurrency,
  formatDate,
  debounce
};
