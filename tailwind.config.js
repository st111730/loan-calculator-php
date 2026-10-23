/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      // Дизайн-система «High-Contrast Editorial» (docs/design-system.md §2, §8.2) —
      // значения живут в src/index.css, классы идут через них
      colors: {
        base: "var(--color-base)",
        surface: "var(--color-surface)",
        accent: "var(--color-accent)",
        "accent-pressed": "var(--color-accent-pressed)",
        "ink-on-accent": "var(--color-ink-on-accent)",
        ink: "var(--color-text)",
        muted: "var(--color-muted)",
        hairline: "var(--color-hairline)",
        error: "var(--color-error)",
        success: "var(--color-success)",
      },
    },
  },
  plugins: [],
};
