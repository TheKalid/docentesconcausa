import { NextResponse } from "next/server";

// Endpoint del asistente. Por ahora responde con un mensaje guía.
// Aquí se conectará la integración de IA (OpenAI u otra) cuando se migre.
export async function POST(request: Request) {
  const { mensaje } = await request.json().catch(() => ({ mensaje: "" }));

  if (!mensaje || typeof mensaje !== "string") {
    return NextResponse.json(
      { respuesta: "No recibí tu mensaje. ¿Puedes escribirlo de nuevo?" },
      { status: 400 }
    );
  }

  const respuesta =
    "¡Gracias por tu mensaje! El asistente con IA está en integración. " +
    'Mientras tanto, revisa nuestras <a href="/planes" style="text-decoration:underline">Planes de Suscripción</a> ' +
    'o escríbenos desde <a href="/servicio-cliente" style="text-decoration:underline">Servicio al Cliente</a>.';

  return NextResponse.json({ respuesta });
}
