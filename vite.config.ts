import path from "node:path"
import { fileURLToPath } from "node:url"

import tailwindcss from "@tailwindcss/vite"
import react from "@vitejs/plugin-react"
import { defineConfig } from "vite"

const rootDir = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  plugins: [react(), tailwindcss()],
  publicDir: false,
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: "public/assets/build",
    rollupOptions: {
      input: {
        main: path.resolve(rootDir, "frontend/src/main.tsx"),
      },
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(rootDir, "frontend/src"),
    },
  },
})
