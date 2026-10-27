const contactNumberPattern = /^\d{11}$/;

function sanitizeContactNumber(value) {
  return String(value || '').replace(/\D/g, '').slice(0, 11);
}

function ContactNumberInput({ value = '', onValueChange, className = '', disabled = false, ...props }) {
  const hasValue = value.length > 0;
  const isInvalid = hasValue && !contactNumberPattern.test(value);

  return (
    <>
      <input
        {...props}
        disabled={disabled}
        type="tel"
        inputMode="numeric"
        pattern="[0-9]{11}"
        minLength={11}
        maxLength={11}
        title="Contact number must be exactly 11 digits."
        aria-invalid={isInvalid}
        value={value}
        onChange={(event) => onValueChange(sanitizeContactNumber(event.target.value))}
        className={className}
      />
      {!disabled && <p className={`mt-1.5 text-xs font-medium ${isInvalid ? 'text-rose-600' : 'text-slate-400'}`}>
        {isInvalid ? 'Contact number must be exactly 11 digits.' : 'Enter exactly 11 digits.'}
      </p>}
    </>
  );
}

export default ContactNumberInput;
