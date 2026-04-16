import { Type } from "@sinclair/typebox";
import { definePluginEntry } from "openclaw/plugin-sdk/plugin-entry";

type PluginConfig = {
  baseUrl?: string;
  apiKey?: string;
};

function buildUrl(baseUrl: string, path: string): string {
  return `${baseUrl.replace(/\/+$/, "")}/${path.replace(/^\/+/, "")}`;
}

async function callMayoristaApi(
  config: PluginConfig,
  path: string,
  options?: {
    method?: "GET" | "POST";
    query?: Record<string, string>;
    body?: Record<string, unknown>;
  },
) {
  const baseUrl = String(config.baseUrl || "").trim();
  const apiKey = String(config.apiKey || "").trim();

  if (!baseUrl || !apiKey) {
    throw new Error("Falta configurar baseUrl/apiKey del plugin mayorista-api.");
  }

  const url = new URL(buildUrl(baseUrl, path));
  for (const [key, value] of Object.entries(options?.query || {})) {
    url.searchParams.set(key, value);
  }

  const response = await fetch(url.toString(), {
    method: options?.method || "GET",
    headers: {
      "X-API-Key": apiKey,
      "Content-Type": "application/json",
    },
    body: options?.body ? JSON.stringify(options.body) : undefined,
  });

  let payload: unknown;
  try {
    payload = await response.json();
  } catch {
    payload = {
      success: false,
      message: `Respuesta no JSON desde ${url.toString()}`,
    };
  }

  if (!response.ok) {
    throw new Error(JSON.stringify(payload, null, 2));
  }

  return payload;
}

function toolResult(payload: unknown) {
  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(payload, null, 2),
      },
    ],
  };
}

export default definePluginEntry({
  id: "mayorista-api",
  name: "Mayorista API Bridge",
  description: "Expone herramientas del sistema mayorista por HTTP.",
  register(api) {
    const config = api.pluginConfig as PluginConfig;

    api.registerTool({
      name: "buscar_cliente",
      description:
        "Busca clientes por nombre, telefono o DNI/CUIT. Usar antes de consultar cuenta corriente o registrar pagos.",
      parameters: Type.Object({
        consulta: Type.String({ minLength: 1 }),
      }),
      async execute(_id, params) {
        return toolResult(
          await callMayoristaApi(config, "clientes", {
            query: { q: params.consulta },
          }),
        );
      },
    });

    api.registerTool({
      name: "consultar_cc_cliente",
      description:
        "Consulta saldo actual y limite de credito de un cliente usando su id.",
      parameters: Type.Object({
        id_cliente: Type.Number(),
      }),
      async execute(_id, params) {
        return toolResult(
          await callMayoristaApi(config, `clientes/${params.id_cliente}/cc`),
        );
      },
    });

    api.registerTool({
      name: "buscar_producto",
      description:
        "Busca productos activos y devuelve stock, precio minorista y precio mayorista.",
      parameters: Type.Object({
        consulta: Type.String({ minLength: 1 }),
      }),
      async execute(_id, params) {
        return toolResult(
          await callMayoristaApi(config, "productos", {
            query: { q: params.consulta },
          }),
        );
      },
    });

    api.registerTool({
      name: "resumen_diario",
      description:
        "Devuelve resumen del dia con ventas y saldo pendiente total.",
      parameters: Type.Object({}),
      async execute() {
        return toolResult(await callMayoristaApi(config, "estadisticas/resumen"));
      },
    });

    api.registerTool({
      name: "crear_cliente_confirmado",
      description:
        "Crea un cliente. Antes de usar esta tool, pedir todos los datos del cliente: nombre, optica, telefono, direccion, localidad, provincia, codigo postal, tipo de documento, DNI, CUIT y condicion IVA. Ejecutar solo despues de confirmar explicitamente en el mismo chat.",
      parameters: Type.Object({
        confirmed: Type.Boolean(),
        nombre: Type.String({ minLength: 1 }),
        optica: Type.Optional(Type.String()),
        telefono: Type.String({ minLength: 1 }),
        direccion: Type.String({ minLength: 1 }),
        localidad: Type.String({ minLength: 1 }),
        provincia: Type.String({ minLength: 1 }),
        codigo_postal: Type.String({ minLength: 1 }),
        tipo_documento: Type.Optional(Type.Number()),
        dni: Type.Optional(Type.String()),
        cuit: Type.Optional(Type.String()),
        condicion_iva: Type.Optional(Type.String()),
      }),
      async execute(_id, params) {
        if (!params.confirmed) {
          throw new Error(
            "No ejecutes crear_cliente_confirmado sin confirmed=true despues de una confirmacion explicita del usuario.",
          );
        }

        return toolResult(
          await callMayoristaApi(config, "clientes", {
            method: "POST",
            body: {
              nombre: params.nombre,
              optica: params.optica || "",
              telefono: params.telefono,
              direccion: params.direccion,
              localidad: params.localidad,
              provincia: params.provincia,
              codigo_postal: params.codigo_postal,
              tipo_documento: params.tipo_documento || 96,
              dni: params.dni || "",
              cuit: params.cuit || "",
              condicion_iva: params.condicion_iva || "Consumidor Final",
            },
          }),
        );
      },
    });

    api.registerTool({
      name: "registrar_pago_cc_confirmado",
      description:
        "Registra un pago de cuenta corriente. Usar solo despues de confirmar explicitamente en el mismo chat.",
      parameters: Type.Object({
        confirmed: Type.Boolean(),
        id_cliente: Type.Number(),
        monto: Type.Number({ exclusiveMinimum: 0 }),
        descripcion: Type.Optional(Type.String()),
        metodo_pago: Type.Optional(Type.Number()),
      }),
      async execute(_id, params) {
        if (!params.confirmed) {
          throw new Error(
            "No ejecutes registrar_pago_cc_confirmado sin confirmed=true despues de una confirmacion explicita del usuario.",
          );
        }

        return toolResult(
          await callMayoristaApi(config, `clientes/${params.id_cliente}/cc/pago`, {
            method: "POST",
            body: {
              monto: params.monto,
              descripcion: params.descripcion || "Pago registrado desde Telegram",
              metodo_pago: params.metodo_pago || 4,
            },
          }),
        );
      },
    });
  },
});
