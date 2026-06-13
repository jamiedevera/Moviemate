import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  async rewrites() {
    return {
      beforeFiles: [
        {
          source: "/start-session",
          destination: "/api/router.php",
        },
        {
          source: "/m/:session*",
          destination: "/api/router.php",
        },
        {
          source: "/logout",
          destination: "/api/router.php",
        },
        {
          source: "/api/auth",
          destination: "/api/router.php",
        },
      ],
    };
  },
};

export default nextConfig;
