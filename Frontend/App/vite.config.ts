import path from "node:path";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  base: "./",
  build: {
    outDir: "../Components/billingresourcesstore/dist/",
    emptyOutDir: true,
    rollupOptions: {
      input: {
        admin: "./admin.html",
        client: "./client.html",
        "admin-settings": "./admin-settings.html",
        "admin-individual-resources": "./admin-individual-resources.html",
      },
    },
  },
});
