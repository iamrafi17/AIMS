const moaDateValue = (value) => value && /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00+08:00` : value;

export const moaDate = (value) => value
  ? new Date(moaDateValue(value)).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' })
  : 'Not set';

export const moaDateInput = (value) => {
  if (!value) return '';
  const parts = new Intl.DateTimeFormat('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Asia/Manila' }).formatToParts(new Date(moaDateValue(value)));
  const part = (type) => parts.find((item) => item.type === type).value;
  return `${part('year')}-${part('month')}-${part('day')}`;
};

export const moaStatus = (moa) => {
  if (moa.status === 'returned' || moa.approval_status === 'returned' || moa.program_status === 'returned') return 'Returned';
  if (moa.vpaa_reviewed_at) return 'VPAA reviewed';
  if (moa.status === 'approved' || moa.approval_status === 'approved') return 'Dean approved · Awaiting VPAA review';
  if (moa.program_status === 'endorsed') return 'Pending Dean review';
  return 'Pending Program Head review';
};
