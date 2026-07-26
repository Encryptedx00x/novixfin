document.addEventListener('DOMContentLoaded', () => {
    if (!Array.isArray(goalsData)) {
        return;
    }

    const modal = document.getElementById('modal-goal');
    const openButton = document.querySelector('[data-open-modal]');
    const closeButton = modal?.querySelector('[data-close-modal]');

    if (!modal || !openButton || !closeButton) {
        return;
    }

    const resetForm = () => {
        modal.querySelector('form').reset();
        modal.querySelector('input[name="id"]').value = '';
    };

    openButton.addEventListener('click', () => {
        resetForm();
        modal.showModal();
    });

    document.querySelectorAll('[data-edit-goal]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = Number(button.getAttribute('data-edit-goal'));
            const goal = goalsData.find((item) => Number(item.id) === id);

            if (!goal) {
                return;
            }

            modal.querySelector('input[name="id"]').value = goal.id;
            modal.querySelector('input[name="title"]').value = goal.title;
            modal.querySelector('input[name="target_amount"]').value = goal.target_amount;
            modal.querySelector('input[name="current_amount"]').value = goal.current_amount;
            modal.querySelector('input[name="deadline"]').value = goal.deadline ?? '';
            modal.querySelector('select[name="type"]').value = goal.type;
            
            if (goal.account_id) {
                modal.querySelector('select[name="account_id"]').value = goal.account_id;
            } else {
                modal.querySelector('select[name="account_id"]').value = '';
            }
            
            if (modal.querySelector('input[name="auto_update"]')) {
                modal.querySelector('input[name="auto_update"]').checked = goal.auto_update !== false;
            }

            modal.showModal();
        });
    });

    const closeModal = () => {
        modal.close();
    };

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeModal();
    });
});

