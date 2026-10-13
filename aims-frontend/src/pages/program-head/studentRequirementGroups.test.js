import assert from 'node:assert/strict';
import test from 'node:test';
import { groupStudentRequirements } from './studentRequirementGroups.js';

test('groups each student and reports partial, complete, and pending review', () => {
  const student = { id: 7, name: 'Juan Dela Cruz' };
  const requirements = [
    { id: 1, student, status: 'approved', reviewer: 'James Bond', submitted_at: '2026-10-02T08:00:00Z', reviewed_at: '2026-10-02T12:00:00Z' },
    { id: 2, student, status: 'approved', reviewer: 'James Bond', submitted_at: '2026-10-03T08:00:00Z', reviewed_at: '2026-10-03T12:00:00Z' },
    { id: 3, student, status: 'rejected', reviewer: 'James Bond', submitted_at: '2026-10-03T09:00:00Z', reviewed_at: '2026-10-03T13:00:00Z' },
    { id: 4, student, status: 'pending', submitted_at: '2026-10-03T10:00:00Z' },
    { id: 5, student, status: 'pending', submitted_at: '2026-10-03T11:00:00Z' },
    { id: 6, student: { id: 8, name: 'Maria Santos' }, status: 'pending', submitted_at: '2026-10-04T08:00:00Z' },
  ];

  const groups = groupStudentRequirements(requirements);
  assert.equal(groups.length, 2);
  assert.equal(groups[1].statusLabel, '3/5 Reviewed');
  assert.equal(groups[1].reviewer, 'James Bond');
  assert.equal(groups[1].submittedAt, '2026-10-02T08:00:00Z');
  assert.equal(groups[1].reviewedAt, '2026-10-03T13:00:00Z');
  assert.equal(groups[0].statusLabel, 'Pending');

  const completed = groupStudentRequirements(requirements.slice(0, 5).map((item) => ({ ...item, status: 'approved' })));
  assert.equal(completed[0].statusLabel, '5/5 Approved');
  assert.equal(completed[0].status, 'approved');
});
