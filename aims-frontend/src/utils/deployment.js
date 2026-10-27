export const deploymentStageLabel = (stage) => ({
  for_program_head_review: 'For Program Head Review',
  for_dean_forwarding: 'For Dean Forwarding',
  forwarded_to_vpaa: 'Forwarded to VPAA',
  superseded: 'Replaced / Withdrawn',
  returned_to_coordinator: 'Returned to Coordinator',
}[stage] || 'Awaiting Coordinator Approval');
