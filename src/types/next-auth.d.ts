import { DefaultSession } from "next-auth";

declare module "next-auth" {
  interface Session {
    user: {
      id: string;
      role: string;
      planActivo: number;
    } & DefaultSession["user"];
  }

  interface User {
    role?: string;
    planActivo?: number;
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    id: string;
    role: string;
    planActivo: number;
  }
}
