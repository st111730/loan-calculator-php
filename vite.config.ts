import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { fileURLToPath, URL } from "node:url";

export default defineConfig({
  plugins: [react()],
  server: {
    // /api уходит same-origin на бэкенд :4000 — там работает новый PHP-бэкенд
    // (php-backend/, composer start); он слушает тот же порт, что и старый
    // Node-сервер. Если PHP поднят на другом порту — переопределить через VITE_API_PROXY.
    proxy: {
      "/api": process.env.VITE_API_PROXY || "http://localhost:4000",
    },
  },
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
});
