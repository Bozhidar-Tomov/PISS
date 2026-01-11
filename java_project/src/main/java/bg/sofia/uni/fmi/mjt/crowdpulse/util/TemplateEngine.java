package bg.sofia.uni.fmi.mjt.crowdpulse.util;

import java.io.IOException;

import java.util.Map;

public class TemplateEngine {
    private static final String TEMPLATE_DIR = "templates/";

    public static String render(String templateName, Map<String, Object> data) throws IOException {
        String resourcePath = TEMPLATE_DIR + templateName + ".html";

        try (var is = TemplateEngine.class.getClassLoader().getResourceAsStream(resourcePath)) {
            if (is == null) {
                return "<h1>Template not found: " + templateName + "</h1>";
            }

            String content = new String(is.readAllBytes(), java.nio.charset.StandardCharsets.UTF_8);

            if (data != null) {
                for (Map.Entry<String, Object> entry : data.entrySet()) {
                    String key = "{{" + entry.getKey() + "}}";
                    String value = String.valueOf(entry.getValue());
                    // Simple replacement, careful with regex in replaceAll, use replace for literal
                    content = content.replace(key, value);
                }
            }
            return content;
        }
    }
}
