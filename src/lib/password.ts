import bcrypt from "bcryptjs";

export function normalizeBcryptHash(hash: string) {
  if (hash.startsWith("$2y$")) {
    return `$2a$${hash.slice(4)}`;
  }

  return hash;
}

export async function verifyPassword(password: string, hash: string) {
  return bcrypt.compare(password, normalizeBcryptHash(hash));
}

export async function hashPassword(password: string) {
  return bcrypt.hash(password, 12);
}
