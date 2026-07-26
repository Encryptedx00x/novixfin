// Força formato de data brasileiro DD/MM/YYYY nos campos type="date"
document.addEventListener('DOMContentLoaded', function() {
    // Define locale brasileiro
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    dateInputs.forEach(input => {
        // Adiciona atributo para forçar formato
        input.setAttribute('data-date-format', 'dd/mm/yyyy');
        
        // Adiciona placeholder visual
        if (!input.value) {
            input.setAttribute('placeholder', 'DD/MM/AAAA');
        }
        
        // Garante que a data seja exibida no formato correto
        input.addEventListener('blur', function() {
            if (this.value) {
                const date = new Date(this.value + 'T00:00:00');
                if (!isNaN(date.getTime())) {
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    
                    // Atualiza o valor no formato ISO para o HTML5
                    this.value = `${year}-${month}-${day}`;
                }
            }
        });
    });
});

