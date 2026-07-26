document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-edit');
    const deleteModal = document.getElementById('modal-delete');
    const deleteAccountModal = document.getElementById('modal-delete-account');
    const deleteAllModal = document.getElementById('modal-delete-all');

    // Modal de edição
    if (modal && Array.isArray(transactionsData)) {
        const closeModal = () => {
            modal.close();
        };

        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.getAttribute('data-edit'));
                const transaction = transactionsData.find((item) => Number(item.id) === id);

                if (!transaction) {
                    return;
                }

                const typeSelect = modal.querySelector('select[name="type"]');
                const categorySelect = modal.querySelector('select[name="category_id"]');
                
                modal.querySelector('input[name="id"]').value = transaction.id;
                
                // Atualiza conta/banco se existir
                const accountSelect = modal.querySelector('select[name="account_id"]');
                if (accountSelect && transaction.account_id) {
                    accountSelect.value = transaction.account_id;
                }
                
                typeSelect.value = transaction.type;
                modal.querySelector('input[name="amount"]').value = Number(transaction.amount);
                modal.querySelector('select[name="currency"]').value = transaction.currency || 'BRL';
                modal.querySelector('input[name="description"]').value = transaction.description ?? '';
                modal.querySelector('input[name="transaction_date"]').value = transaction.transaction_date;
                modal.querySelector('input[name="is_effective"]').checked = transaction.is_effective ?? true;
                modal.querySelector('input[name="is_recurring"]').checked = transaction.is_recurring ?? false;

                const recurrenceTypeSelect = modal.querySelector('select[name="recurrence_type"]');
                const recurrenceDaySelect = modal.querySelector('select[name="recurrence_day"]');
                const recurrenceEndInput = modal.querySelector('input[name="recurrence_end_date"]');
                const recurrenceLimitModeSelect = modal.querySelector('select[name="recurrence_limit_mode"]');
                const recurrenceTotalInput = modal.querySelector('input[name="recurrence_total_occurrences"]');
                if (recurrenceTypeSelect) {
                    recurrenceTypeSelect.value = transaction.recurrence_type || 'monthly';
                }
                if (recurrenceDaySelect && transaction.recurrence_day !== null && transaction.recurrence_day !== undefined) {
                    recurrenceDaySelect.value = String(transaction.recurrence_day);
                } else if (recurrenceDaySelect) {
                    recurrenceDaySelect.value = '';
                }
                if (recurrenceEndInput) {
                    recurrenceEndInput.value = transaction.recurrence_end_date || '';
                }
                if (recurrenceTotalInput) {
                    recurrenceTotalInput.value = transaction.recurrence_total_occurrences || '';
                }
                if (recurrenceLimitModeSelect) {
                    if (transaction.recurrence_total_occurrences) {
                        recurrenceLimitModeSelect.value = 'count';
                    } else if (transaction.recurrence_end_date) {
                        recurrenceLimitModeSelect.value = 'date';
                    } else {
                        recurrenceLimitModeSelect.value = 'never';
                    }
                }
                toggleRecurrenceFieldsEdit();
                
                // Atualiza campo de data de efetivação
                const effectiveDateInput = modal.querySelector('input[name="effective_date"]');
                if (effectiveDateInput) {
                    // Carrega a data existente se houver, ou deixa vazio
                    if (transaction.effective_date) {
                        effectiveDateInput.value = transaction.effective_date;
                    } else {
                        effectiveDateInput.value = '';
                    }
                    
                    // Define a data mínima como a data da transação
                    const transactionDate = new Date(transaction.transaction_date);
                    if (!isNaN(transactionDate.getTime())) {
                        effectiveDateInput.min = transactionDate.toISOString().split('T')[0];
                    }
                }
                
                // Atualiza visibilidade do campo de data de efetivação baseado no estado atual
                toggleEffectiveDateEdit();

                // Filtra categorias baseado no tipo
                const allCategoryOptions = Array.from(categorySelect.options).map(opt => ({
                    value: opt.value,
                    text: opt.text,
                    type: opt.getAttribute('data-type')
                }));

                const filterCategories = () => {
                    const selectedType = typeSelect.value;
                    const currentValue = categorySelect.value;
                    categorySelect.innerHTML = '';

                    allCategoryOptions.forEach(opt => {
                        if (opt.type === selectedType) {
                            const option = new Option(opt.text, opt.value);
                            option.setAttribute('data-type', opt.type);
                            categorySelect.add(option);
                        }
                    });

                    // Tenta manter a categoria selecionada, senão seleciona a primeira
                    if (categorySelect.querySelector(`option[value="${currentValue}"]`)) {
                        categorySelect.value = currentValue;
                    } else if (categorySelect.options.length > 0) {
                        categorySelect.value = categorySelect.options[0].value;
                    }
                };

                typeSelect.removeEventListener('change', filterCategories);
                typeSelect.addEventListener('change', filterCategories);
                filterCategories();

                modal.showModal();
            });
        });

        const closeBtn = modal.querySelector('[data-close-modal]');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        modal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeModal();
        });
    }

    // Modal de deletar transação individual
    if (deleteModal) {
        const closeDeleteModal = () => {
            deleteModal.close();
        };

        document.querySelectorAll('[data-delete]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-delete');
                deleteModal.querySelector('#delete-id').value = id;
                deleteModal.querySelector('#delete-form').action = '/actions/transactions/delete.php';
                deleteModal.showModal();
            });
        });

        const closeDeleteBtn = deleteModal.querySelector('[data-close-delete-modal]');
        if (closeDeleteBtn) {
            closeDeleteBtn.addEventListener('click', closeDeleteModal);
        }
        deleteModal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDeleteModal();
        });
    }

    // Modal de deletar todas transações de uma conta
    if (deleteAccountModal) {
        const closeDeleteAccountModal = () => {
            deleteAccountModal.close();
        };

        document.querySelectorAll('[data-delete-account]').forEach((button) => {
            button.addEventListener('click', () => {
                const accountId = button.getAttribute('data-delete-account');
                deleteAccountModal.querySelector('#delete-account-id').value = accountId;
                deleteAccountModal.showModal();
            });
        });

        const closeDeleteAccountBtn = deleteAccountModal.querySelector('[data-close-delete-account-modal]');
        if (closeDeleteAccountBtn) {
            closeDeleteAccountBtn.addEventListener('click', closeDeleteAccountModal);
        }
        deleteAccountModal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDeleteAccountModal();
        });
    }

    // Modal de deletar todas as transações
    if (deleteAllModal) {
        const closeDeleteAllModal = () => {
            deleteAllModal.close();
        };

        const deleteAllBtn = document.querySelector('[data-delete-all]');
        if (deleteAllBtn) {
            deleteAllBtn.addEventListener('click', () => {
                deleteAllModal.showModal();
            });
        }

        const closeDeleteAllBtn = deleteAllModal.querySelector('[data-close-delete-all-modal]');
        if (closeDeleteAllBtn) {
            closeDeleteAllBtn.addEventListener('click', closeDeleteAllModal);
        }
        deleteAllModal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDeleteAllModal();
        });
    }
    
    window.toggleRecurrenceFieldsEdit = function() {
        const isRecurring = document.getElementById('modal-is-recurring');
        const container = document.getElementById('modal-recurrence-fields');
        if (!isRecurring || !container) {
            return;
        }
        if (isRecurring.checked) {
            container.classList.remove('hidden');
            toggleRecurrenceDayEdit();
            toggleRecurrenceLimitFieldsEdit();
        } else {
            container.classList.add('hidden');
            const typeSelect = document.getElementById('modal-recurrence-type');
            const daySelect = document.getElementById('modal-recurrence-day');
            const limitSelect = document.getElementById('modal-recurrence-limit-mode');
            const endInput = document.getElementById('modal-recurrence-end-date');
            const totalInput = document.getElementById('modal-recurrence-total-occurrences');
            if (typeSelect) typeSelect.value = 'none';
            if (daySelect) daySelect.value = '';
            if (limitSelect) limitSelect.value = 'never';
            if (endInput) endInput.value = '';
            if (totalInput) totalInput.value = '';
            toggleRecurrenceLimitFieldsEdit();
        }
    };

    window.toggleRecurrenceDayEdit = function() {
        const recurrenceType = document.getElementById('modal-recurrence-type');
        const dayContainer = document.getElementById('modal-recurrence-day-container');
        if (!recurrenceType || !dayContainer) {
            return;
        }
        if (recurrenceType.value === 'weekly') {
            dayContainer.classList.remove('hidden');
        } else {
            dayContainer.classList.add('hidden');
            const daySelect = document.getElementById('modal-recurrence-day');
            if (daySelect) daySelect.value = '';
        }
    };

    // Função para mostrar/ocultar campo de data de efetivação
    window.toggleRecurrenceLimitFieldsEdit = function() {
        const modeSelect = document.getElementById('modal-recurrence-limit-mode');
        const dateContainer = document.getElementById('modal-recurrence-end-date-container');
        const countContainer = document.getElementById('modal-recurrence-total-container');
        const endInput = document.getElementById('modal-recurrence-end-date');
        const totalInput = document.getElementById('modal-recurrence-total-occurrences');
        if (!modeSelect || !dateContainer || !countContainer) {
            return;
        }

        dateContainer.classList.toggle('hidden', modeSelect.value !== 'date');
        countContainer.classList.toggle('hidden', modeSelect.value !== 'count');

        if (modeSelect.value !== 'date' && endInput) {
            endInput.value = '';
        }
        if (modeSelect.value !== 'count' && totalInput) {
            totalInput.value = '';
        }
    };

    window.toggleEffectiveDateEdit = function() {
        const isEffective = document.getElementById('modal-is-effective');
        const container = document.getElementById('modal-effective-date-container');
        const effectiveDateInput = document.getElementById('modal-effective-date');
        const transactionDateInput = document.getElementById('modal-date');
        
        if (!container || !isEffective) {
            return;
        }
        
        if (!isEffective.checked) {
            // Mostra o campo quando não está efetivada
            container.classList.remove('hidden');
            container.style.display = 'block';
            
            if (effectiveDateInput) {
                // Define a data mínima como a data da transação (ou hoje se não tiver)
                let minDate = new Date().toISOString().split('T')[0];
                if (transactionDateInput && transactionDateInput.value) {
                    const transactionDate = new Date(transactionDateInput.value);
                    if (!isNaN(transactionDate.getTime())) {
                        minDate = transactionDate.toISOString().split('T')[0];
                    }
                }
                effectiveDateInput.min = minDate;
            }
        } else {
            // Oculta o campo quando está efetivada
            container.classList.add('hidden');
            container.style.display = 'none';
            
            if (effectiveDateInput) {
                // Limpa o campo quando oculta (será salvo como null)
                effectiveDateInput.value = '';
            }
        }
    };
});

