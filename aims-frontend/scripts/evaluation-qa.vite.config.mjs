import { mergeConfig } from "vite";
import base from "../vite.config.js";

// Isolated browser QA: the ordinary development proxy remains unchanged.
export default mergeConfig(base, {
  server: {
    host: "127.0.0.1",
    port: 5174,
    strictPort: true,
    proxy: { "/api": { target: "http://127.0.0.1:8001", changeOrigin: true } },
  },
});
