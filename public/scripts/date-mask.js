// Máscara de data brasileira DD/MM/YYYY
function applyDateMask(input) {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
        
        if (value.length >= 8) {
            value = value.substring(0, 8);
        }
        
        let formatted = '';
        if (value.length > 0) {
            formatted = value.substring(0, 2);
            if (value.length >= 3) {
                formatted += '/' + value.substring(2, 4);
            }
            if (value.length >= 5) {
                formatted += '/' + value.substring(4, 8);
            }
        }
        
        e.target.value = formatted;
    });
}

// Converte data brasileira DD/MM/YYYY para YYYY-MM-DD
function brDateToIso(brDate) {
    if (!brDate || brDate.length !== 10) return '';
    const parts = brDate.split('/');
    if (parts.length !== 3) return '';
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
}

// Converte data ISO YYYY-MM-DD para brasileira DD/MM/YYYY
function isoToBrDate(isoDate) {
    if (!isoDate || isoDate.length !== 10) return '';
    const parts = isoDate.split('-');
    if (parts.length !== 3) return '';
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

// Aplica máscara em todos os campos com classe 'date-br'
document.addEventListener('DOMContentLoaded', function() {
    const dateInputs = document.querySelectorAll('.date-br');
    dateInputs.forEach(input => {
        applyDateMask(input);
        
        // Se houver valor inicial, converte para formato brasileiro
        if (input.value && input.value.includes('-')) {
            input.value = isoToBrDate(input.value);
        }
    });
    
    // Ao submeter formulário, converte datas brasileiras para ISO
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const dateInputs = form.querySelectorAll('.date-br');
            dateInputs.forEach(input => {
                if (input.value && input.value.includes('/')) {
                    // Cria um campo hidden com a data no formato ISO
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = input.name;
                    hiddenInput.value = brDateToIso(input.value);
                    
                    // Remove o name do input original para não enviar
                    input.removeAttribute('name');
                    
                    // Adiciona o campo hidden ao form
                    form.appendChild(hiddenInput);
                }
            });
        });
    });
});

