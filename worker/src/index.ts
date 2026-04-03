import { YServer } from "y-partyserver";
import { routePartykitRequest } from "partyserver";

/**
 * Durable Object that runs a Yjs sync relay for one "room" (one post being edited).
 * Uses WebSocket Hibernation so idle editing sessions cost nothing.
 * y-partyserver handles:
 *   - Yjs sync protocol (SyncStep1/SyncStep2)
 *   - Awareness relay (cursor positions, user presence)
 *   - Persisting document state to DO storage
 */
export class Collaboration extends YServer {
  static options = {
    hibernate: true,
  };
}

interface Env {
  Collaboration: DurableObjectNamespace<Collaboration>;
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);

    // Health check
    if (url.pathname === "/") {
      return new Response(
        JSON.stringify({ status: "ok", service: "pantheon-rtc-poc" }),
        { headers: { "Content-Type": "application/json" } }
      );
    }

    // Use PartyServer's built-in routing: /parties/<bindingName>/<roomId>
    const response = await routePartykitRequest(request, env);
    if (response) {
      return response;
    }

    return new Response("Not found", { status: 404 });
  },
} satisfies ExportedHandler<Env>;
