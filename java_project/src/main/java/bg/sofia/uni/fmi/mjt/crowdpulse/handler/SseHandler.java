package bg.sofia.uni.fmi.mjt.crowdpulse.handler;

import bg.sofia.uni.fmi.mjt.crowdpulse.model.Command;
import bg.sofia.uni.fmi.mjt.crowdpulse.service.CommandService;
import com.sun.net.httpserver.Headers;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;

public class SseHandler implements HttpHandler {
    private final CommandService commandService;

    public SseHandler() {
        this.commandService = new CommandService();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {
        Headers headers = exchange.getResponseHeaders();
        headers.set("Content-Type", "text/event-stream");
        headers.set("Cache-Control", "no-cache");
        headers.set("Connection", "keep-alive");
        headers.set("Access-Control-Allow-Origin", "*");

        exchange.sendResponseHeaders(200, 0);

        try (OutputStream os = exchange.getResponseBody()) {
            // Keep connection open and poll for updates
            // In a real production server, we would use async processing or separate
            // threads properly.
            // For this project requirement (HttpServer), managing long-lived connections
            // can exhaust threads if not careful.
            // But basic polling loop is acceptable for demo scale.

            long lastTimestamp = 0;

            while (true) {
                Command activeCmd = commandService.getActiveCommand();

                if (activeCmd != null && activeCmd.getTimestamp() > lastTimestamp) {
                    lastTimestamp = activeCmd.getTimestamp();
                    String eventData = "data: " + activeCmd.getCommandData() + "\n\n";
                    os.write(eventData.getBytes(StandardCharsets.UTF_8));
                    os.flush();
                } else {
                    // Send heartbeats to keep connection alive
                    os.write(": heartbeat\n\n".getBytes(StandardCharsets.UTF_8));
                    os.flush();
                }

                try {
                    Thread.sleep(1000); // 1-second polling interval
                } catch (InterruptedException e) {
                    break;
                }
            }
        } catch (IOException e) {
            // Client disconnected
            System.out.println("SSE Client disconnected");
        }
    }
}
