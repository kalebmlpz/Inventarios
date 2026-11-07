// Los cálculos de presentación usan centavos enteros; el servidor confirma el precio.
window.FloreriaMoney = {
    cents(value) {
        const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(String(value))
        if (!match) return null
        return BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0'))
    },
    decimal(cents) { return `${cents / 100n}.${String(cents % 100n).padStart(2, '0')}` },
    amount(value, currency = 'MXN') {
        const cents = this.cents(value)
        if (cents === null) return 'Sin precio registrado'
        const whole = String(cents / 100n).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
        return `$${whole}.${String(cents % 100n).padStart(2, '0')} ${currency}`
    }
}
