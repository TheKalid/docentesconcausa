-- CreateSchema
CREATE SCHEMA IF NOT EXISTS "public";

-- CreateEnum
CREATE TYPE "Role" AS ENUM ('USER', 'ADMIN');

-- CreateTable
CREATE TABLE "usuarios" (
    "id" SERIAL NOT NULL,
    "nombre" TEXT NOT NULL,
    "email" TEXT NOT NULL,
    "password" TEXT,
    "estado" TEXT NOT NULL DEFAULT 'pendiente',
    "telefono" TEXT,
    "plan_activo" INTEGER DEFAULT 0,
    "plan_id" TEXT,
    "fecha_proximo_pago" DATE,
    "stripe_customer_id" TEXT,
    "stripe_subscription_id" TEXT,
    "token_verificacion" TEXT,
    "reset_token" TEXT,
    "reset_token_expires_at" TIMESTAMP(3),
    "usos_plan_basico" INTEGER NOT NULL DEFAULT 0,
    "usos_plan_intermedio" INTEGER NOT NULL DEFAULT 0,
    "usos_fisica" INTEGER NOT NULL DEFAULT 0,
    "usos_evaluacion_diagnostica" INTEGER NOT NULL DEFAULT 0,
    "usos_evaluacion" INTEGER NOT NULL DEFAULT 0,
    "usos_protocolos" INTEGER NOT NULL DEFAULT 0,
    "usos_bitacora" INTEGER NOT NULL DEFAULT 0,
    "usos_contexto" INTEGER NOT NULL DEFAULT 0,
    "usos_examenes" INTEGER NOT NULL DEFAULT 5,
    "fecha_inicio_ciclo" DATE,
    "psicologo_solicitado" BOOLEAN NOT NULL DEFAULT false,
    "status" TEXT NOT NULL DEFAULT 'activo',
    "usos_diarios_padres" INTEGER NOT NULL DEFAULT 2,
    "ultimo_uso_padres" DATE,
    "referido_por" TEXT,
    "contexto_guardado" TEXT,
    "role" "Role" NOT NULL DEFAULT 'USER',
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "usuarios_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "grupos" (
    "id" SERIAL NOT NULL,
    "usuario_id" INTEGER NOT NULL,
    "grado_planeacion" TEXT,
    "duracion_planeacion" TEXT,
    "numero_total_estudiantes" INTEGER,
    "auditivos" INTEGER,
    "visuales" INTEGER,
    "kinestesicos" INTEGER,
    "refuerzo_lecto_general" TEXT,
    "estrategias_lecto_general" TEXT,
    "refuerzo_calculo" TEXT,
    "estrategias_calculo" TEXT,
    "refuerzo_operaciones" TEXT,
    "estrategias_operaciones" TEXT,
    "tiene_no_lectoescritura" TEXT,
    "numero_alumnos_lectoescritura" INTEGER,
    "refuerzo_lecto_adecuacion" TEXT,
    "estrategias_lecto_adecuacion" TEXT,

    CONSTRAINT "grupos_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "recursos" (
    "id" SERIAL NOT NULL,
    "titulo" TEXT NOT NULL,
    "descripcion" TEXT,
    "categoria" TEXT,
    "archivo" TEXT,
    "ruta" TEXT,
    "fecha_subida" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "recursos_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "historial_uso" (
    "id" SERIAL NOT NULL,
    "usuario_id" INTEGER NOT NULL,
    "herramienta" TEXT NOT NULL,
    "ip_usuario" TEXT NOT NULL,
    "fecha" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "historial_uso_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "convenio_cetl" (
    "id" SERIAL NOT NULL,
    "usuario_id" INTEGER NOT NULL,
    "activo" BOOLEAN NOT NULL DEFAULT true,
    "fecha_alta" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "convenio_cetl_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE UNIQUE INDEX "usuarios_email_key" ON "usuarios"("email");

-- CreateIndex
CREATE UNIQUE INDEX "grupos_usuario_id_key" ON "grupos"("usuario_id");

-- CreateIndex
CREATE UNIQUE INDEX "convenio_cetl_usuario_id_key" ON "convenio_cetl"("usuario_id");

-- AddForeignKey
ALTER TABLE "grupos" ADD CONSTRAINT "grupos_usuario_id_fkey" FOREIGN KEY ("usuario_id") REFERENCES "usuarios"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "historial_uso" ADD CONSTRAINT "historial_uso_usuario_id_fkey" FOREIGN KEY ("usuario_id") REFERENCES "usuarios"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "convenio_cetl" ADD CONSTRAINT "convenio_cetl_usuario_id_fkey" FOREIGN KEY ("usuario_id") REFERENCES "usuarios"("id") ON DELETE CASCADE ON UPDATE CASCADE;

