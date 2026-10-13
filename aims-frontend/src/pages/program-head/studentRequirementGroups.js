export function groupStudentRequirements(requirements) {
  const groups = new Map();

  for (const requirement of requirements) {
    const student = requirement.student || {};
    const key = requirement.student_internship_id ?? student.id ?? student.student_id ?? `requirement-${requirement.id}`;
    if (!groups.has(key)) groups.set(key, { key, student, requirements: [] });
    groups.get(key).requirements.push(requirement);
  }

  return [...groups.values()].map((group) => {
    const items = group.requirements;
    const reviewed = items.filter((item) => item.status === 'approved' || item.status === 'rejected');
    const approved = items.filter((item) => item.status === 'approved').length;
    const rejected = items.some((item) => item.status === 'rejected');
    const reviewers = [...new Set(reviewed.map((item) => item.reviewer).filter(Boolean))];
    const dates = items.map((item) => item.submitted_at).filter(Boolean).sort();
    const reviewedDates = items.map((item) => item.reviewed_at).filter(Boolean).sort();

    return {
      ...group,
      submittedAt: dates[0] || null,
      reviewedAt: reviewedDates.at(-1) || null,
      reviewer: reviewers.length === 1 ? reviewers[0] : reviewers.length > 1 ? 'Multiple reviewers' : null,
      reviewedCount: reviewed.length,
      status: approved === items.length ? 'approved' : reviewed.length === items.length && rejected ? 'rejected' : reviewed.length ? 'reviewed' : 'pending',
      statusLabel: approved === items.length
        ? `${approved}/${items.length} Approved`
        : reviewed.length === items.length && rejected ? `${reviewed.length}/${items.length} Reviewed · Rejected`
          : reviewed.length ? `${reviewed.length}/${items.length} Reviewed` : 'Pending',
    };
  }).sort((a, b) => (b.submittedAt || '').localeCompare(a.submittedAt || ''));
}
