/**
 * Модальные окна
 */

export function initModals() {
    const modal = document.getElementById('modal');
    const modalClose = document.querySelector('.modal-close');
    const modalOverlay = document.querySelector('.modal-overlay');

    if (modalClose) {
        modalClose.addEventListener('click', () => {
            closeModal();
        });
    }

    if (modalOverlay) {
        modalOverlay.addEventListener('click', () => {
            closeModal();
        });
    }

    // Закрытие по Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

export function openModal(title, content) {
    const modal = document.getElementById('modal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');

    if (modalTitle) modalTitle.textContent = title;
    if (modalBody) modalBody.innerHTML = content;

    if (modal) {
        modal.style.display = 'flex';
        // Блокируем скролл body
        document.body.style.overflow = 'hidden';
    }
}

export function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.style.display = 'none';
        // Восстанавливаем скролл body
        document.body.style.overflow = '';
    }
}

// Для обратной совместимости с не-module кодом
if (typeof window !== 'undefined') {
    window.openModal = openModal;
    window.closeModal = closeModal;
}