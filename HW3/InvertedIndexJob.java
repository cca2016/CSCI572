import java.io.IOException;
import java.util.StringTokenizer;
import java.util.HashMap;
import java.util.stream.Collectors;
import org.apache.hadoop.conf.Configuration;
import org.apache.hadoop.fs.Path;
import org.apache.hadoop.io.Text;
import org.apache.hadoop.mapreduce.Job;
import org.apache.hadoop.mapreduce.Mapper;
import org.apache.hadoop.mapreduce.Reducer;
import org.apache.hadoop.mapreduce.lib.input.FileInputFormat;
import org.apache.hadoop.mapreduce.lib.output.FileOutputFormat;

public class InvertedIndexJob {
 
  public static class TokenizerMapper extends Mapper<Object, Text, Text, Text>{
   
    private Text word = new Text();     // Store 'word' of document 
    private Text document = new Text(); // Store the 'id' of documment

    // Key - Dont care
    // Value - Document/Text (Tab between document id and text of document)
    public void map(Object key, Text value, Context context) throws IOException, InterruptedException {
     
      String [] splitted = value.toString().split("\t",2); // Data isn't clean (Instructions LIED) => Need to split on 1st tab to get doc id

      document.set(splitted[0]); // Every map "chunk" consists of 1 document

      /*
      Clean the text before tokenization:
      1. Make everything lowecase
      2. Replace special characters, numbers, and with space
      3. "Normalize" spaces
      */
      String filtered = splitted[1].toLowerCase();
      filtered = filtered.replaceAll("[^a-z\\s]"," ");
      filtered = filtered.replaceAll("\\s+"," ");

      StringTokenizer itr = new StringTokenizer(filtered);


      while (itr.hasMoreTokens()) { // Iterate through tokens
        word.set(itr.nextToken());
        context.write(word, document); // Spit-out <key:token, value:doc_id> per token
      }

    }

  }

  
  public static class HashReducer extends Reducer<Text,Text,Text,Text> {

    private Text result = new Text(); // To hold string for of hashmap
    
    // Key - Word
    // Values - Documents
    public void reduce(Text key, Iterable<Text> values, Context context) throws IOException, InterruptedException {

      HashMap<String,Integer> stats = new HashMap<String,Integer>(); // To hold counts of word in each document

      for (Text val : values) {  
        stats.put(val.toString(), stats.getOrDefault(val.toString(), 0) + 1); // getOrDefault(val,0) if val isnt present -> return 0
      }
      
      String together = new String("");

      // Collect as string 
      for (String i : stats.keySet()){
        together = together + i + ":" + String.valueOf(stats.get(i)) + "\t";
      }

      result.set(together.substring(0,together.length() - 1)); // Convert String to Text

      context.write(key, result); // Spit-out <key:word, value:formatted string>
    }
  }

  public static void main(String[] args) throws Exception {
    Configuration conf = new Configuration();
    
    Job job = Job.getInstance(conf, "Inverted_Index");
    
    job.setJarByClass(InvertedIndexJob.class);

    job.setMapperClass(TokenizerMapper.class);
    job.setReducerClass(HashReducer.class);

    job.setOutputKeyClass(Text.class);
    job.setOutputValueClass(Text.class);

    FileInputFormat.addInputPath(job, new Path(args[0]));
    FileOutputFormat.setOutputPath(job, new Path(args[1]));
    
    System.exit(job.waitForCompletion(true) ? 0 : 1);
  }
}