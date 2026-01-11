package bg.sofia.uni.fmi.mjt.crowdpulse.repository;

import bg.sofia.uni.fmi.mjt.crowdpulse.config.DatabaseConfig;
import bg.sofia.uni.fmi.mjt.crowdpulse.model.Command;
import com.mongodb.client.MongoCollection;
import com.mongodb.client.model.Filters;
import com.mongodb.client.model.Updates;
import org.bson.Document;
import java.util.UUID;

import com.mongodb.client.model.Sorts;

public class CommandRepository {
    private final MongoCollection<Document> collection;

    public CommandRepository() {
        this.collection = DatabaseConfig.getDatabase().getCollection("commands");
    }

    public void save(Command command) {
        if (command.getId() == null) {
            command.setId(UUID.randomUUID().toString());
        }
        Document doc = mapToDocument(command);
        collection.insertOne(doc);
    }

    public Command findActiveCommand() {
        Document doc = collection.find(Filters.eq("is_active", true))
                .sort(Sorts.descending("timestamp"))
                .first();
        return mapToCommand(doc);
    }

    public void deactivateAll() {
        collection.updateMany(Filters.eq("is_active", true), Updates.set("is_active", false));
    }

    private Command mapToCommand(Document doc) {
        if (doc == null)
            return null;
        Command cmd = new Command();
        cmd.setId(doc.getString("_id"));
        cmd.setCommandType(doc.getString("command_type"));
        cmd.setCommandData(doc.getString("command_data"));
        cmd.setActive(doc.getBoolean("is_active", false));
        // Handle timestamp being potentially Long or Integer from DB
        Number ts = doc.get("timestamp", Number.class);
        cmd.setTimestamp(ts != null ? ts.longValue() : 0);
        return cmd;
    }

    private Document mapToDocument(Command cmd) {
        Document doc = new Document();
        doc.append("_id", cmd.getId());
        doc.append("command_type", cmd.getCommandType());
        doc.append("command_data", cmd.getCommandData());
        doc.append("is_active", cmd.isActive());
        doc.append("timestamp", cmd.getTimestamp());
        return doc;
    }
}
