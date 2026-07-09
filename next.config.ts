import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Genera una build autocontenida para imágenes Docker ligeras.
  output: "standalone",
};

export default nextConfig;
