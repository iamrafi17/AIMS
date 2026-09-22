const levels = {
  weak: { label: 'Weak', segments: 1, textClass: 'text-red-600 dark:text-red-400', barClass: 'bg-red-500' },
  medium: { label: 'Medium', segments: 2, textClass: 'text-yellow-600 dark:text-yellow-400', barClass: 'bg-yellow-500' },
  strong: { label: 'Strong', segments: 3, textClass: 'text-green-600 dark:text-green-400', barClass: 'bg-green-500' },
};

export function getPasswordStrength(value) {
  if (!value) return null;
  const variety = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/].filter((pattern) => pattern.test(value)).length;
  if (value.length >= 12 && variety >= 3) return levels.strong;
  if (value.length >= 8 && variety >= 2) return levels.medium;
  return levels.weak;
}
